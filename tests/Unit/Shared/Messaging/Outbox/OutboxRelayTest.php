<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Outbox;

use App\Shared\Messaging\Outbox\ClaimedOutboxMessage;
use App\Shared\Messaging\Outbox\OutboxMessageTypes;
use App\Shared\Messaging\Outbox\OutboxRelay;
use App\Shared\Messaging\Outbox\OutboxStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class OutboxRelayTest extends TestCase
{
    private const string NOW = '2026-10-25 12:00:00';
    private const string IN_FIVE_SECONDS = '2026-10-25 12:00:05';
    private const string BROKER_FAILURE = 'Broker unavailable.';
    private const int FIRST_ID = 1;
    private const int SECOND_ID = 2;

    public function testPublishesClaimedMessagesAndRemovesThem(): void
    {
        $store = $this->createMock(OutboxStoreInterface::class);
        $store->expects(self::once())->method('claim')->with(
            [ExampleOutboxMessage::messageType()],
            OutboxRelay::BATCH_SIZE,
            new DateTimeImmutable(self::NOW),
            new DateTimeImmutable(self::NOW)->modify(sprintf('+%d seconds', OutboxRelay::LEASE_SECONDS)),
        )->willReturn([self::claimed(self::FIRST_ID, self::NOW), self::claimed(self::SECOND_ID, self::IN_FIVE_SECONDS)]);
        $removed = [];
        $store->method('remove')->willReturnCallback(static function (int $id) use (&$removed): void {
            $removed[] = $id;
        });
        $published = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function (Envelope $envelope) use (&$published): Envelope {
                $published[] = $envelope;

                return $envelope;
            },
        );

        $count = self::relay($store, $bus)->relayBatch();

        self::assertSame(2, $count);
        self::assertSame([self::FIRST_ID, self::SECOND_ID], $removed);
        self::assertNull($published[0]->last(DelayStamp::class));
        self::assertSame(5000, $published[1]->last(DelayStamp::class)?->getDelay());
    }

    public function testKeepsMessageWhenPublishingFailsSoItIsRetriedAfterTheLease(): void
    {
        $store = $this->createMock(OutboxStoreInterface::class);
        $store->method('claim')->willReturn([self::claimed(self::FIRST_ID, self::NOW)]);
        $store->expects(self::never())->method('remove');
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException(self::BROKER_FAILURE));

        $this->expectException(RuntimeException::class);
        self::relay($store, $bus)->relayBatch();
    }

    private static function claimed(int $id, string $deliverAt): ClaimedOutboxMessage
    {
        return new ClaimedOutboxMessage(
            $id,
            ExampleOutboxMessage::messageType(),
            ['reference' => 'ref-' . $id],
            new DateTimeImmutable($deliverAt),
        );
    }

    private static function relay(OutboxStoreInterface $store, MessageBusInterface $bus): OutboxRelay
    {
        return new OutboxRelay(
            $store,
            new ImmediateTransactionRunner(),
            $bus,
            new OutboxMessageTypes([ExampleOutboxMessage::class]),
            new MockClock(self::NOW),
        );
    }
}
