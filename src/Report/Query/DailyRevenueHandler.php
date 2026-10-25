<?php

declare(strict_types=1);

namespace App\Report\Query;

use App\Report\ReadModel\DailyRevenue;
use App\Report\Repository\ReportReadRepositoryInterface;

final class DailyRevenueHandler
{
    public function __construct(private readonly ReportReadRepositoryInterface $reports)
    {
    }

    /** @return list<DailyRevenue> */
    public function handle(DailyRevenueQuery $query): array
    {
        return $this->reports->dailyRevenue($query->period);
    }
}
