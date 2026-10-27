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
 * Read-only sales reports. SQL in the read repository filters and adds up the orders; this
 * service works on its small result: the running total and the ranks.
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
        $days = [];
        $runningTotal = null;

        foreach ($this->reports->dailySales($period) as $sales) {
            $runningTotal = $runningTotal?->add($sales->revenue) ?? $sales->revenue;
            $days[] = new DailyRevenue($sales->day, $sales->orders, $sales->revenue, $runningTotal);
        }

        return $days;
    }

    /** @return list<TopProduct> */
    public function topProducts(ReportPeriod $period, int $limit): array
    {
        $products = [];

        foreach ($this->reports->bestSellingProducts($period, $limit) as $sales) {
            $products[] = new TopProduct(
                count($products) + 1,
                $sales->productId,
                $sales->sku,
                $sales->name,
                $sales->unitsSold,
                $sales->revenue,
            );
        }

        return $products;
    }

    /** @return list<ReportedOrder> */
    public function orders(OrderStatusEnum $status, DateTimeImmutable $since): array
    {
        return $this->reports->ordersByStatus($status, $since, self::ORDER_REPORT_LIMIT);
    }
}
