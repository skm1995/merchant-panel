<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

use Money\Money;

final readonly class MonthRevenue
{
    /** @param string $month in YYYY-MM format */
    public function __construct(public string $month, public Money $revenue)
    {
    }
}
