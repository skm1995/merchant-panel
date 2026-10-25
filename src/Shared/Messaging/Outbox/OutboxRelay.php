<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Publishes outbox messages to their Messenger transports with at-least-once delivery.
 * It only claims message types it can restore, so unknown rows stay for inspection.
 *
 * A short transaction leases a batch; publishing happens outside it. A message is removed
 * only after it was published, so a relay that dies in between leaves the message to be
 * claimed again when its lease expires. Messages due in the future are published with a
 * DelayStamp and wait in RabbitMQ. Consumers must therefore be idempotent.
 */
final class OutboxRelay
{
    public const int BATCH_SIZE = 100;
    public const int LEASE_SECONDS = 30;
    private const string LEASE_MODIFIER_FORMAT = '+%d seconds';
    private const int MILLISECONDS_PER_SECOND = 1000;

    public function __construct(
        private readonly OutboxStoreInterface $store,
        private readonly TransactionRunnerInterface $transactions,
        private readonly MessageBusInterface $bus,
        private readonly OutboxMessageTypes $types,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Publishes one batch and returns how many messages were published.
     */
    public function relayBatch(): int
    {
        $messageTypes = $this->types->names();

        if ($messageTypes === []) {
            return 0;
        }

        $now = $this->clock->now();
        $claimed = $this->transactions->run(
            fn (): array => $this->store->claim(
                $messageTypes,
                self::BATCH_SIZE,
                $now,
                $now->modify(sprintf(self::LEASE_MODIFIER_FORMAT, self::LEASE_SECONDS)),
            ),
            IsolationLevelEnum::READ_COMMITTED,
        );

        foreach ($claimed as $message) {
            $delay = ($message->deliverAt->getTimestamp() - $now->getTimestamp()) * self::MILLISECONDS_PER_SECOND;
            $this->bus->dispatch(new Envelope(
                $this->types->restore($message->messageType, $message->payload),
                $delay > 0 ? [new DelayStamp($delay)] : [],
            ));
            $this->store->remove($message->id);
        }

        return count($claimed);
    }
}
