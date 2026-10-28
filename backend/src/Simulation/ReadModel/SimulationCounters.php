<?php

declare(strict_types=1);

namespace App\Simulation\ReadModel;

final readonly class SimulationCounters
{
    public const string ORDERS_PLACED = 'orders_placed';
    public const string ORDERS_PAID = 'orders_paid';
    public const string SOLD_OUT = 'sold_out';
    public const string FAILED_REQUESTS = 'failed_requests';

    public function __construct(
        public int $ordersPlaced,
        public int $ordersPaid,
        public int $soldOut,
        public int $failedRequests,
    ) {
    }

    /** @param array<string, int> $counts */
    public static function fromCounts(array $counts): self
    {
        return new self(
            $counts[self::ORDERS_PLACED] ?? 0,
            $counts[self::ORDERS_PAID] ?? 0,
            $counts[self::SOLD_OUT] ?? 0,
            $counts[self::FAILED_REQUESTS] ?? 0,
        );
    }
}
