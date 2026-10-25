<?php

declare(strict_types=1);

namespace App\Report\Query;

final readonly class DailyRevenueQuery
{
    public function __construct(public ReportPeriod $period)
    {
    }
}
