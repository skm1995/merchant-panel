<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\Query\ReportPeriod;
use App\Report\ReadModel\DailyRevenue;
use App\Report\ReadModel\TopProduct;

interface ReportReadRepositoryInterface
{
    /** @return list<DailyRevenue> */
    public function dailyRevenue(ReportPeriod $period): array;

    /** @return list<TopProduct> */
    public function topProducts(ReportPeriod $period, int $limit): array;
}
