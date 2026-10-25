<?php

declare(strict_types=1);

namespace App\Order\ReadModel;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use Money\Money;

/**
 * Read model of an order, loaded straight from SQL without the write model.
 */
final readonly class OrderView
{
    /** @param list<OrderLineView> $lines */
    public function __construct(
        public OrderId $id,
        public OrderStatusEnum $status,
        public array $lines,
        public Money $total,
        public DateTimeImmutable $reservationExpiresAt,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
