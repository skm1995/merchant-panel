<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\InvalidEmailException;
use App\Auth\Message\PasswordResetEmailMessage;
use App\Auth\Repository\PasswordResetTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\ConfirmationToken;
use App\Auth\Service\RequestPasswordResetService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Support\RecordingOutbox;

final class RequestPasswordResetServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string TYPED_EMAIL = '  Person@Example.com ';
    private const string NOW = '2026-10-29 12:00:00';
    private const string IN_AN_HOUR = '2026-10-29 13:00:00';

    private FrozenClock $clock;
    private RecordingOutbox $outbox;
    /** @var array<string, array{hash: string, createdAt: DateTimeImmutable, expiresAt: DateTimeImmutable}> */
    private array $storedLinks = [];

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(self::NOW);
        $this->outbox = new RecordingOutbox();
    }

    public function testEmailsAConfirmedAccountALinkThatWorksForAnHour(): void
    {
        $this->service(self::confirmedUser())->execute(self::TYPED_EMAIL);

        [$message] = $this->outbox->messages();
        self::assertInstanceOf(PasswordResetEmailMessage::class, $message);
        self::assertSame(self::EMAIL, $message->email);
        self::assertSame($this->storedLinks[self::EMAIL]['hash'], ConfirmationToken::fromString($message->token)->hash(), 'Only the hash of the emailed token is stored.');
        self::assertEquals(new DateTimeImmutable(self::IN_AN_HOUR), $this->storedLinks[self::EMAIL]['expiresAt']);
    }

    public function testSendsNothingToAnUnknownOrUnconfirmedAddress(): void
    {
        $this->service(null)->execute(self::EMAIL);
        $this->service(new User(self::EMAIL, 'hash', new DateTimeImmutable()))->execute(self::EMAIL);

        self::assertSame([], $this->outbox->messages());
        self::assertSame([], $this->storedLinks);
    }

    public function testSendsANewLinkOnlyOnceFiveMinutesHavePassed(): void
    {
        $service = $this->service(self::confirmedUser());
        $service->execute(self::EMAIL);
        $firstHash = $this->storedLinks[self::EMAIL]['hash'];

        $this->clock->sleep(RequestPasswordResetService::COOLDOWN_SECONDS - 1);
        $service->execute(self::EMAIL);
        self::assertCount(1, $this->outbox->messages());
        self::assertSame($firstHash, $this->storedLinks[self::EMAIL]['hash']);

        $this->clock->sleep(1);
        $service->execute(self::EMAIL);
        self::assertCount(2, $this->outbox->messages());
        self::assertNotSame($firstHash, $this->storedLinks[self::EMAIL]['hash'], 'The new link replaces the old one.');
    }

    public function testRejectsAnInvalidAddress(): void
    {
        $this->expectException(InvalidEmailException::class);

        $this->service(self::confirmedUser())->execute('not-an-email');
    }

    private static function confirmedUser(): User
    {
        return new User(self::EMAIL, 'hash', new DateTimeImmutable(), new DateTimeImmutable());
    }

    private function service(?User $user): RequestPasswordResetService
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($user);

        return new RequestPasswordResetService($users, $this->tokenRepository(), $this->outbox, new ImmediateTransactionRunner(), $this->clock);
    }

    /**
     * Keeps the links in this test's $storedLinks, so assertions read state instead of calls.
     */
    private function tokenRepository(): PasswordResetTokenRepositoryInterface
    {
        $links = &$this->storedLinks;

        return new class ($links) implements PasswordResetTokenRepositoryInterface {
            /** @param array<string, array{hash: string, createdAt: DateTimeImmutable, expiresAt: DateTimeImmutable}> $links */
            public function __construct(private array &$links)
            {
            }

            public function findCreatedAt(string $email): ?DateTimeImmutable
            {
                return ($this->links[$email] ?? null)['createdAt'] ?? null;
            }

            public function store(string $email, string $tokenHash, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): void
            {
                $this->links[$email] = ['hash' => $tokenHash, 'createdAt' => $createdAt, 'expiresAt' => $expiresAt];
            }

            public function findEmailByValidToken(string $tokenHash, DateTimeImmutable $now): ?string
            {
                return null;
            }

            public function delete(string $email): void
            {
                unset($this->links[$email]);
            }
        };
    }
}
