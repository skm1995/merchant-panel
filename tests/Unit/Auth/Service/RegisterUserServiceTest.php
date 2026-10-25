<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\ResendLimitReachedException;
use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\ConfirmationToken;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegisterUserServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string WRONG_PASSWORD = 'incorrect-password';
    private const string INVALID_EMAIL = 'invalid-email';
    private const string BROKER_FAILURE = 'Broker unavailable.';
    private const int TOKEN_HASH_LENGTH = 64;

    public function testStoresOnlyTokenHashAndDispatchesConfirmationEmail(): void
    {
        $tokenHash = null;
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn(null);
        $users->expects(self::once())->method('create')->willReturnCallback(self::newUser(...));
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::never())->method('storeWithinResendLimit');
        $tokens->expects(self::once())->method('store')->with(
            self::EMAIL,
            self::callback(static function (string $hash) use (&$tokenHash): bool {
                $tokenHash = $hash;

                return strlen($hash) === self::TOKEN_HASH_LENGTH;
            }),
            self::isInstanceOf(DateTimeImmutable::class),
        );
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::once())->method('dispatch')->with(self::callback(
            static function (ConfirmationEmailMessage $message) use (&$tokenHash): bool {
                return $message->email === self::EMAIL
                    && $tokenHash === ConfirmationToken::fromString($message->token)->hash();
            },
        ));

        new RegisterUserService(new UserAuthService($users), $tokens, $queue)->execute(self::EMAIL, self::PASSWORD);
    }

    public function testValidationFailureDoesNotLookForAnExistingAccount(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('findByEmail');
        $users->expects(self::never())->method('create');
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::never())->method('dispatch');
        $service = new RegisterUserService(
            new UserAuthService($users),
            $this->createStub(EmailVerificationTokenRepositoryInterface::class),
            $queue,
        );

        $this->expectException(DomainException::class);
        $service->execute(self::INVALID_EMAIL, self::PASSWORD);
    }

    public function testResendsConfirmationForExistingUnverifiedUserWithCorrectPassword(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::exactly(2))->method('findByEmail')->with(self::EMAIL)->willReturn(self::existingUser());
        $users->expects(self::never())->method('create');
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::never())->method('store');
        $tokens->expects(self::once())->method('storeWithinResendLimit')->with(
            self::EMAIL,
            self::isString(),
            self::isInstanceOf(DateTimeImmutable::class),
            self::isInstanceOf(DateTimeImmutable::class),
            RegisterUserService::RESEND_WINDOW_SECONDS,
            RegisterUserService::MAXIMUM_RESEND_ATTEMPTS,
        )->willReturn(true);
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::once())->method('dispatch');

        new RegisterUserService(new UserAuthService($users), $tokens, $queue)->execute(self::EMAIL, self::PASSWORD);
    }

    public function testRejectsDuplicateEmailWhenPasswordDoesNotMatchUnverifiedUser(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(self::existingUser());
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::never())->method('dispatch');
        $service = new RegisterUserService(
            new UserAuthService($users),
            $this->createStub(EmailVerificationTokenRepositoryInterface::class),
            $queue,
        );

        $this->expectException(DuplicateEmailException::class);
        $service->execute(self::EMAIL, self::WRONG_PASSWORD);
    }

    public function testRegistrationCanBeRetriedAfterQueueDispatchFailure(): void
    {
        $user = self::existingUser();
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::exactly(3))
            ->method('findByEmail')
            ->with(self::EMAIL)
            ->willReturnOnConsecutiveCalls(null, $user, $user);
        $users->expects(self::once())->method('create')->willReturn($user);
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('store');
        $tokens->expects(self::once())->method('storeWithinResendLimit')->willReturn(true);
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $dispatchCount = 0;
        $queue->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function () use (&$dispatchCount): void {
                if ($dispatchCount++ === 0) {
                    throw new RuntimeException(self::BROKER_FAILURE);
                }
            },
        );
        $service = new RegisterUserService(new UserAuthService($users), $tokens, $queue);

        try {
            $service->execute(self::EMAIL, self::PASSWORD);
            self::fail('Expected the broker failure to be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame(self::BROKER_FAILURE, $exception->getMessage());
        }

        $service->execute(self::EMAIL, self::PASSWORD);
    }

    public function testStopsWhenTheConfirmationResendLimitIsReached(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(self::existingUser());
        $tokens = $this->createStub(EmailVerificationTokenRepositoryInterface::class);
        $tokens->method('storeWithinResendLimit')->willReturn(false);
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::never())->method('dispatch');
        $service = new RegisterUserService(new UserAuthService($users), $tokens, $queue);

        $this->expectException(ResendLimitReachedException::class);
        $service->execute(self::EMAIL, self::PASSWORD);
    }

    private static function newUser(string $email, string $passwordHash): User
    {
        return new User($email, $passwordHash, new DateTimeImmutable());
    }

    private static function existingUser(): User
    {
        return self::newUser(self::EMAIL, password_hash(self::PASSWORD, PASSWORD_DEFAULT));
    }
}
