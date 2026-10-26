<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Report\Service\ReportPeriod;
use App\Report\ReadModel\DailyRevenue;
use App\Report\ReadModel\ReportedOrder;
use App\Report\ReadModel\TopProduct;
use DateTimeImmutable;

interface ReportReadRepositoryInterface
{
    /** @return list<DailyRevenue> */
    public function dailyRevenue(ReportPeriod $period): array;

    /** @return list<TopProduct> */
    public function topProducts(ReportPeriod $period, int $limit): array;

    /**
     * Newest orders with the status placed since the given time, with their product names.
     *
     * @return list<ReportedOrder>
     */
    public function ordersByStatus(OrderStatusEnum $status, DateTimeImmutable $since, int $limit): array;
}
