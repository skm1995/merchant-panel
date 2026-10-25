<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Repository\EmailVerificationTokenRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\Auth\AuthActionResult;
use App\Service\Auth\ConfirmationEmailMessage;
use App\Service\Auth\ConfirmationEmailQueueInterface;
use App\Service\Auth\RegisterUserService;
use App\Service\Auth\UserAuthService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegistrationConfirmationServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string EMAIL_KEY = 'email';
    private const string PASSWORD_KEY = 'password';
    private const string CONFIRMATION_PATH = '/confirmation-sent';

    public function testRegistrationStoresOnlyTokenHashAndDispatchesEmailMessage(): void
    {
        $tokenHash = null;
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn(null);
        $users->expects(self::once())->method('create')->willReturnCallback(
            static fn (string $email, string $passwordHash): User => new User(
                $email,
                $passwordHash,
                new DateTimeImmutable(),
            ),
        );
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('store')->with(
            self::EMAIL,
            self::callback(static function (string $hash) use (&$tokenHash): bool {
                $tokenHash = $hash;

                return strlen($hash) === 64;
            }),
            self::isInstanceOf(DateTimeImmutable::class),
        );
        $queue = $this->createMock(ConfirmationEmailQueueInterface::class);
        $queue->expects(self::once())->method('dispatch')->with(
            self::callback(
                static function (ConfirmationEmailMessage $message) use (&$tokenHash): bool {
                    $messageTokenHash = $message->token
                        |> (static fn (string $token): string => hash('sha256', $token));

                    return $message->email === self::EMAIL
                        && is_string($tokenHash)
                        && hash_equals($tokenHash, $messageTokenHash);
                },
            ),
        );
        $service = new RegisterUserService(new UserAuthService($users), $tokens, $queue);

        $result = $service->execute([
            self::EMAIL_KEY => self::EMAIL,
            self::PASSWORD_KEY => self::PASSWORD,
        ]);

        self::assertInstanceOf(AuthActionResult::class, $result);
        self::assertSame(self::CONFIRMATION_PATH, $result->redirectPath);
    }
}
