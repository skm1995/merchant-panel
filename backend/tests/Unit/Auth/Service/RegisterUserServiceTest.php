<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\ResendLimitReachedException;
use App\Auth\Message\AccountExistsEmailMessage;
use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\ConfirmationToken;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use DateTimeImmutable;
use App\Shared\Database\Enum\IsolationLevelEnum;
use DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\CheapPasswordHash;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Support\RecordingOutbox;
use Tests\Unit\Support\FrozenClock;

final class RegisterUserServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string WRONG_PASSWORD = 'incorrect-password';
    private const string INVALID_EMAIL = 'invalid-email';
    private const string NOW = '2026-10-25 12:00:00';
    private const int TOKEN_HASH_LENGTH = 64;

    public function testStoresOnlyTokenHashAndDispatchesConfirmationEmail(): void
    {
        $tokenHash = null;
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects(self::once())->method('create')->willReturnCallback(self::newUser(...));
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('store')->with(
            self::EMAIL,
            self::callback(static function (string $hash) use (&$tokenHash): bool {
                $tokenHash = $hash;

                return strlen($hash) === self::TOKEN_HASH_LENGTH;
            }),
            self::isInstanceOf(DateTimeImmutable::class),
        );
        $outbox = new RecordingOutbox();
        $transactions = new ImmediateTransactionRunner();

        $this->service($users, $tokens, $outbox, $transactions)->execute(self::EMAIL, self::PASSWORD);

        [$message] = $outbox->messages();
        self::assertInstanceOf(ConfirmationEmailMessage::class, $message);
        self::assertSame(self::EMAIL, $message->email);
        self::assertSame($tokenHash, ConfirmationToken::fromString($message->token)->hash(), 'The email carries the token whose hash was stored.');
        self::assertEquals(new DateTimeImmutable(self::NOW), $outbox->recorded[0]['deliverAt']);
        self::assertSame(IsolationLevelEnum::READ_COMMITTED, $transactions->lastIsolationLevel);
    }

    public function testRejectsAnInvalidEmailWithoutCreatingAnAccountOrSendingMail(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('create');
        $outbox = new RecordingOutbox();

        try {
            $this->service($users, $this->createStub(EmailVerificationTokenRepositoryInterface::class), $outbox)
                ->execute(self::INVALID_EMAIL, self::PASSWORD);
            self::fail('An invalid email must be rejected.');
        } catch (DomainException) {
            self::assertSame([], $outbox->messages());
        }
    }

    public function testResendsConfirmationForExistingUnverifiedUserWithCorrectPassword(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(self::existingUser());
        $users->expects(self::never())->method('create');
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('storeWithinResendLimit')->with(
            self::EMAIL,
            self::isString(),
            self::isInstanceOf(DateTimeImmutable::class),
            self::isInstanceOf(DateTimeImmutable::class),
            RegisterUserService::RESEND_WINDOW_SECONDS,
            RegisterUserService::MAXIMUM_RESEND_ATTEMPTS,
        )->willReturn(true);
        $outbox = new RecordingOutbox();

        $this->service($users, $tokens, $outbox)->execute(self::EMAIL, self::PASSWORD);

        self::assertContainsOnlyInstancesOf(ConfirmationEmailMessage::class, $outbox->messages());
        self::assertCount(1, $outbox->messages());
    }

    public function testTellsTheOwnerOfAnExistingAccountInsteadOfRevealingItToTheCaller(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(self::existingUser());
        $outbox = new RecordingOutbox();

        $this->service($users, $this->createStub(EmailVerificationTokenRepositoryInterface::class), $outbox)->execute(self::EMAIL, self::WRONG_PASSWORD);

        self::assertEquals([new AccountExistsEmailMessage(self::EMAIL)], $outbox->messages());
    }

    public function testStopsWhenTheConfirmationResendLimitIsReached(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(self::existingUser());
        $tokens = $this->createStub(EmailVerificationTokenRepositoryInterface::class);
        $tokens->method('storeWithinResendLimit')->willReturn(false);
        $outbox = new RecordingOutbox();

        try {
            $this->service($users, $tokens, $outbox)->execute(self::EMAIL, self::PASSWORD);
            self::fail('The resend limit must stop the email.');
        } catch (ResendLimitReachedException) {
            self::assertSame([], $outbox->messages());
        }
    }

    private function service(
        UserRepositoryInterface $users,
        EmailVerificationTokenRepositoryInterface $tokens,
        RecordingOutbox $outbox,
        ?ImmediateTransactionRunner $transactions = null,
    ): RegisterUserService {
        return new RegisterUserService(
            new UserAuthService($users),
            $tokens,
            $outbox,
            $transactions ?? new ImmediateTransactionRunner(),
            new FrozenClock(self::NOW),
        );
    }

    private static function newUser(string $email, string $passwordHash): User
    {
        return new User($email, $passwordHash, new DateTimeImmutable());
    }

    private static function existingUser(): User
    {
        return self::newUser(self::EMAIL, CheapPasswordHash::of(self::PASSWORD));
    }
}
