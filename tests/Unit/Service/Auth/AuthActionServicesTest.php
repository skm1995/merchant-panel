<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Repository\EmailVerificationTokenRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\Auth\AuthActionResult;
use App\Service\Auth\LoginUserService;
use App\Service\Auth\LogoutUserService;
use App\Service\Auth\RegisterUserService;
use App\Service\Auth\ConfirmationEmailQueueInterface;
use App\Service\Auth\ConfirmEmailService;
use App\Service\Auth\UserAuthService;
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

    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->originalSession = $_SESSION;
        $_SESSION = [];
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

    public function testLoginUserServiceAuthenticatesAndReturnsRedirect(): void
    {
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user = new User(self::EMAIL, $hash, new DateTimeImmutable(), new DateTimeImmutable());
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn($user);
        $service = new LoginUserService(new UserAuthService($repository));

        $result = $service->execute(self::credentials());

        self::assertSame(self::HOME_PATH, $result->redirectPath);
        self::assertSame(self::EMAIL, $_SESSION[self::SESSION_EMAIL_KEY]);
    }

    public function testLogoutUserServiceRemovesSessionUser(): void
    {
        $_SESSION[self::SESSION_EMAIL_KEY] = self::EMAIL;

        $result = new LogoutUserService()->execute();

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
