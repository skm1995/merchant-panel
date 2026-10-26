<?php

declare(strict_types=1);

namespace App\Order\ReadModel;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use Money\Money;

final readonly class OrderSummary
{
    public function __construct(
        public OrderId $id,
        public OrderStatusEnum $status,
        public Money $total,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
