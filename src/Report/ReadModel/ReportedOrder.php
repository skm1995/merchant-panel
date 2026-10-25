<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

use DateTimeImmutable;
use Money\Money;

final readonly class ReportedOrder
{
    public function __construct(
        public int $orderNumber,
        public ?string $products,
        public Money $total,
        public DateTimeImmutable $placedAt,
    ) {
    }
}
