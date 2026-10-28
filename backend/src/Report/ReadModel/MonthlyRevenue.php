<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

use Money\Money;

final readonly class MonthlyRevenue
{
    /**
     * @param string $month in YYYY-MM format
     * @param ?float $changePercent null when the same month a year earlier had no revenue
     */
    public function __construct(
        public string $month,
        public Money $revenue,
        public Money $previousYearRevenue,
        public ?float $changePercent,
    ) {
    }
}
