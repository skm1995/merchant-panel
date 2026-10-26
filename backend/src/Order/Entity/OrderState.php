<?php

declare(strict_types=1);

namespace App\Order\Entity;

use App\Order\Enum\OrderStatusEnum;

/**
 * Write-side snapshot of a placed order used to change its status.
 */
final readonly class OrderState
{
    /** @param array<int, int> $quantities quantities keyed by product id */
    public function __construct(
        public OrderId $id,
        public OrderStatusEnum $status,
        public int $version,
        public array $quantities,
    ) {
    }
}
