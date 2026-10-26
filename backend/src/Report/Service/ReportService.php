<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ReadModel\DailyRevenue;
use App\Report\ReadModel\ReportedOrder;
use App\Report\ReadModel\TopProduct;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Report\ValueObject\ReportPeriod;
use DateTimeImmutable;

/**
 * Read-only sales reports, computed by hand-written SQL in the read repository.
 */
final class ReportService
{
    public const int ORDER_REPORT_LIMIT = 50;

    public function __construct(private readonly ReportReadRepositoryInterface $reports)
    {
    }

    /** @return list<DailyRevenue> */
    public function dailyRevenue(ReportPeriod $period): array
    {
        return $this->reports->dailyRevenue($period);
    }

    /** @return list<TopProduct> */
    public function topProducts(ReportPeriod $period, int $limit): array
    {
        return $this->reports->topProducts($period, $limit);
    }

    /** @return list<ReportedOrder> */
    public function orders(OrderStatusEnum $status, DateTimeImmutable $since): array
    {
        return $this->reports->ordersByStatus($status, $since, self::ORDER_REPORT_LIMIT);
    }
}
