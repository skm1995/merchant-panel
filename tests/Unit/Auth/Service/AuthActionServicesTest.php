<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\AuthActionResult;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\LogoutUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\UserAuthService;
use App\Shared\Http\Session;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthActionServicesTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string INVALID_EMAIL = 'invalid-email';
    private const string EMAIL_KEY = 'email';
    private const string PASSWORD_KEY = 'password';
    private const string SESSION_EMAIL_KEY = 'user_email';
    private const string HOME_PATH = '/';
    private const string CONFIRMATION_PATH = '/confirmation-sent';
    private const string INVALID_EMAIL_MESSAGE = 'Enter a valid email address.';
    private const string RESEND_LIMIT_MESSAGE = 'You have reached the email confirmation resend limit. Try again later.';

    /** @var array<string, mixed> */
    private array $originalSession;
    private Session $session;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->session = new Session();
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testRegisterUserServiceReturnsConfirmationRedirectWithoutLoggingIn(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn(null);
        $repository->expects(self::once())->method('create')->willReturnCallback(
            static fn (string $email, string $passwordHash): User => new User(
                $email,
                $passwordHash,
                new DateTimeImmutable(),
            ),
        );
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('store');
        $tokens->expects(self::never())->method('resend');
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::once())->method('dispatch');
        $service = new RegisterUserService(new UserAuthService($repository), $tokens, $queue);

        $result = $service->execute(self::credentials());

        self::assertSame(self::CONFIRMATION_PATH, $result->redirectPath);
        self::assertArrayNotHasKey(self::SESSION_EMAIL_KEY, $_SESSION);
    }

    public function testRegisterUserServiceReturnsValidationFailure(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('create');
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::never())->method('store');
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::never())->method('dispatch');
        $service = new RegisterUserService(new UserAuthService($repository), $tokens, $queue);

        $result = $service->execute([
            self::EMAIL_KEY => self::INVALID_EMAIL,
            self::PASSWORD_KEY => self::PASSWORD,
        ]);

        self::assertSame(self::INVALID_EMAIL_MESSAGE, $result->errorMessage);
        self::assertSame(self::INVALID_EMAIL, $result->email);
    }

    public function testRegisterResendsConfirmationForExistingUnverifiedUserWithCorrectPassword(): void
    {
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user = new User(self::EMAIL, $hash, new DateTimeImmutable());
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::exactly(2))
            ->method('findByEmail')
            ->with(self::EMAIL)
            ->willReturn($user);
        $repository->expects(self::never())->method('create');
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::never())->method('store');
        $tokens->expects(self::once())->method('resend')->willReturn(true);
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::once())->method('dispatch');
        $service = new RegisterUserService(new UserAuthService($repository), $tokens, $queue);

        $result = $service->execute(self::credentials());

        self::assertSame(self::CONFIRMATION_PATH, $result->redirectPath);
    }

    public function testRegistrationCanBeRetriedAfterQueueDispatchFailure(): void
    {
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user = new User(self::EMAIL, $hash, new DateTimeImmutable());
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::exactly(3))
            ->method('findByEmail')
            ->with(self::EMAIL)
            ->willReturnOnConsecutiveCalls(null, $user, $user);
        $repository->expects(self::once())
            ->method('create')
            ->willReturn($user);
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('store');
        $tokens->expects(self::once())->method('resend')->willReturn(true);
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $dispatchCount = 0;
        $queue->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function () use (&$dispatchCount): void {
                if ($dispatchCount++ === 0) {
                    throw new \RuntimeException('Broker unavailable.');
                }
            },
        );
        $service = new RegisterUserService(new UserAuthService($repository), $tokens, $queue);

        try {
            $service->execute(self::credentials());
            self::fail('Expected the broker failure to be propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Broker unavailable.', $exception->getMessage());
        }

        $result = $service->execute(self::credentials());

        self::assertSame(self::CONFIRMATION_PATH, $result->redirectPath);
    }

    public function testRegisterStopsWhenTheConfirmationResendLimitIsReached(): void
    {
        $user = new User(
            self::EMAIL,
            password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            new DateTimeImmutable(),
        );
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findByEmail')->willReturn($user);
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('resend')->willReturn(false);
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::never())->method('dispatch');
        $service = new RegisterUserService(new UserAuthService($repository), $tokens, $queue);

        $result = $service->execute(self::credentials());

        self::assertSame(self::RESEND_LIMIT_MESSAGE, $result->errorMessage);
    }

    public function testLoginUserServiceAuthenticatesAndReturnsRedirect(): void
    {
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user = new User(self::EMAIL, $hash, new DateTimeImmutable(), new DateTimeImmutable());
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn($user);
        $service = new LoginUserService(new UserAuthService($repository), $this->session);

        $result = $service->execute(self::credentials());

        self::assertSame(self::HOME_PATH, $result->redirectPath);
        self::assertSame(self::EMAIL, $_SESSION[self::SESSION_EMAIL_KEY]);
    }

    public function testLogoutUserServiceRemovesSessionUser(): void
    {
        $_SESSION[self::SESSION_EMAIL_KEY] = self::EMAIL;

        $result = new LogoutUserService($this->session)->execute();

        self::assertSame(self::HOME_PATH, $result->redirectPath);
        self::assertArrayNotHasKey(self::SESSION_EMAIL_KEY, $_SESSION);
    }

    public function testAuthActionResultCreatesFailureResult(): void
    {
        $result = AuthActionResult::failure(self::INVALID_EMAIL_MESSAGE, self::INVALID_EMAIL);

        self::assertNull($result->redirectPath);
        self::assertSame(self::INVALID_EMAIL_MESSAGE, $result->errorMessage);
        self::assertSame(self::INVALID_EMAIL, $result->email);
    }

    /** @return array<string, string> */
    private static function credentials(): array
    {
        return [
            self::EMAIL_KEY => self::EMAIL,
            self::PASSWORD_KEY => self::PASSWORD,
        ];
    }
}
