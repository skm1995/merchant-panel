<?php

declare(strict_types=1);

namespace Tests\Functional\Messaging;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Messaging\Outbox\PdoOutbox;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\Messaging\Outbox\ExampleOutboxMessage;

final class PdoOutboxTest extends TestCase
{
    private const string DELETE_QUERY = 'DELETE FROM outbox_messages WHERE message_type = :type';
    private const string LOCK_ALL_QUERY = 'SELECT id FROM outbox_messages WHERE message_type = :type FOR UPDATE';
    private const string NOW = '2026-10-25 12:00:00';
    private const string AFTER_LEASE = '2026-10-25 12:01:00';
    private const string LATER = '2026-10-25 12:15:00';
    private const int BATCH_SIZE = 10;

    private PDO $pdo;
    private PdoOutbox $outbox;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->outbox = new PdoOutbox($this->pdo);
        $this->clear();
    }

    protected function tearDown(): void
    {
        $this->clear();
    }

    public function testClaimsStoredMessagesInOrderWithTheirDeliveryTime(): void
    {
        $this->outbox->add(new ExampleOutboxMessage('first'), new DateTimeImmutable(self::NOW));
        $this->outbox->add(new ExampleOutboxMessage('second'), new DateTimeImmutable(self::LATER));

        $claimed = $this->claim(self::NOW);

        self::assertSame([['reference' => 'first'], ['reference' => 'second']], array_column($claimed, 'payload'));
        self::assertEquals(new DateTimeImmutable(self::LATER), $claimed[1]->deliverAt);
        self::assertSame([], $this->claim(self::NOW), 'Claimed messages stay leased.');
        self::assertCount(2, $this->claim(self::AFTER_LEASE), 'An expired lease is claimed again.');
    }

    public function testSkipsMessagesLockedByAnotherRelay(): void
    {
        $this->outbox->add(new ExampleOutboxMessage('locked'), new DateTimeImmutable(self::NOW));
        $otherRelay = DatabaseConnection::createFromEnvironment();
        $otherRelay->beginTransaction();
        $otherRelay->prepare(self::LOCK_ALL_QUERY)->execute(['type' => ExampleOutboxMessage::messageType()]);

        $whileLocked = $this->claim(self::NOW);
        $otherRelay->commit();

        self::assertSame([], $whileLocked);
        self::assertCount(1, $this->claim(self::NOW));
    }

    public function testRemovesPublishedMessages(): void
    {
        $this->outbox->add(new ExampleOutboxMessage('done'), new DateTimeImmutable(self::NOW));
        [$claimed] = $this->claim(self::NOW);

        $this->outbox->remove($claimed->id);

        self::assertSame([], $this->claim(self::AFTER_LEASE));
    }

    /** @return list<\App\Shared\Messaging\Outbox\ClaimedOutboxMessage> */
    private function claim(string $now): array
    {
        return new PdoTransactionRunner($this->pdo)->run(fn (): array => $this->outbox->claim(
            [ExampleOutboxMessage::messageType()],
            self::BATCH_SIZE,
            new DateTimeImmutable($now),
            new DateTimeImmutable($now)->modify('+30 seconds'),
        ));
    }

    private function clear(): void
    {
        $this->pdo->prepare(self::DELETE_QUERY)->execute(['type' => ExampleOutboxMessage::messageType()]);
    }
}
