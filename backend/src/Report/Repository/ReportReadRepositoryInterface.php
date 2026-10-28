<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ValueObject\ReportPeriod;
use App\Report\ReadModel\DailySales;
use App\Report\ReadModel\HeatmapCell;
use App\Report\ReadModel\MonthRevenue;
use App\Report\ReadModel\ProductPace;
use App\Report\ReadModel\ProductSales;
use App\Report\ReadModel\ReportedOrder;
use DateTimeImmutable;
use Money\Currency;

interface ReportReadRepositoryInterface
{
    /**
     * Paid orders and their revenue per day, oldest day first; days without sales are left out.
     *
     * @return list<DailySales>
     */
    public function dailySales(ReportPeriod $period): array;

    /**
     * Products with the highest revenue from paid orders, highest first; equal revenue is
     * ordered by product id.
     *
     * @return list<ProductSales>
     */
    public function bestSellingProducts(ReportPeriod $period, int $limit): array;

    /**
     * Units sold per product in the half-open range of days, from the daily sales summary,
     * ordered by how soon the available stock runs out at that pace; equal outlooks are
     * ordered by product id. Products without sales in the range are left out.
     *
     * @return list<ProductPace>
     */
    public function productSalesBetween(DateTimeImmutable $startsAt, DateTimeImmutable $endsBefore, int $limit): array;

    /**
     * Paid orders in any currency counted per ISO weekday and hour, ordered by both;
     * combinations without orders are left out.
     *
     * @return list<HeatmapCell>
     */
    public function paidOrdersByWeekdayAndHour(ReportPeriod $period): array;

    /**
     * Revenue per month in the currency, from the daily sales summary, oldest month first;
     * months without revenue are left out.
     *
     * @return list<MonthRevenue>
     */
    public function revenueByMonth(Currency $currency, DateTimeImmutable $startsAt, DateTimeImmutable $endsBefore): array;

    /**
     * Newest orders with the status placed since the given time, with their product names.
     *
     * @return list<ReportedOrder>
     */
    public function ordersByStatus(OrderStatusEnum $status, DateTimeImmutable $since, int $limit): array;
}
