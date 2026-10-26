<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

use DateTimeImmutable;
use Money\Money;

final readonly class DailyRevenue
{
    public function __construct(
        public DateTimeImmutable $day,
        public int $orders,
        public Money $revenue,
        public Money $runningTotal,
    ) {
    }
}
