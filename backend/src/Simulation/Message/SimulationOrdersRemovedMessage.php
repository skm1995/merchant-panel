<?php

declare(strict_types=1);

namespace App\Simulation\Message;

use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;
use DateTimeImmutable;

/**
 * Recorded through the outbox when a new simulation removes the orders of finished ones, so
 * the features that follow sales, such as the daily sales summary, recompute the days those
 * paid orders were placed on.
 */
#[Message(transport: 'orders')]
final readonly class SimulationOrdersRemovedMessage implements OutboxMessageInterface
{
    private const string TYPE = 'simulation.orders_removed';
    private const string DAYS_KEY = 'days';
    private const string DAY_FORMAT = 'Y-m-d';

    /** @param list<DateTimeImmutable> $days */
    public function __construct(public array $days)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        /** @var list<string> $days */
        $days = $data[self::DAYS_KEY];

        return new self(array_map(static fn (string $day): DateTimeImmutable => new DateTimeImmutable($day), $days));
    }

    public function toArray(): array
    {
        return [self::DAYS_KEY => array_map(static fn (DateTimeImmutable $day): string => $day->format(self::DAY_FORMAT), $this->days)];
    }
}
