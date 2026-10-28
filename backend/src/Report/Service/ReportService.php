<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ReadModel\DailyRevenue;
use App\Report\ReadModel\HeatmapCell;
use App\Report\ReadModel\MonthlyRevenue;
use App\Report\ReadModel\ReportedOrder;
use App\Report\ReadModel\StockOutlook;
use App\Report\ReadModel\TopProduct;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Report\ValueObject\ReportPeriod;
use DateTimeImmutable;
use Money\Currency;
use Money\Money;
use Psr\Clock\ClockInterface;

/**
 * Read-only sales reports. SQL in the read repository filters and adds up the orders; this
 * service works on its small result: running totals, ranks, paces, and comparisons.
 */
final class ReportService
{
    public const int ORDER_REPORT_LIMIT = 50;
    public const int OUTLOOK_DAYS = 28;
    private const int MONTHS = 12;
    private const int MONTHS_WITH_PREVIOUS_YEAR = 24;
    private const int PERCENT = 100;
    private const int PACE_DECIMALS = 2;
    private const int CHANGE_DECIMALS = 1;
    private const string TODAY = 'today';
    private const string FIRST_DAY_OF_THIS_MONTH = 'first day of this month';
    private const string DAYS_BACK_FORMAT = '-%d days';
    private const string MONTHS_BACK_FORMAT = '-%d months';
    private const string NEXT_MONTH = '+1 month';
    private const string YEAR_BACK = '-1 year';
    private const string MONTH_FORMAT = 'Y-m';

    public function __construct(
        private readonly ReportReadRepositoryInterface $reports,
        private readonly ClockInterface $clock,
    ) {
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

    /**
     * Products that sell out first at the pace of the last full days, ending yesterday.
     *
     * @return list<StockOutlook>
     */
    public function stockOutlook(int $limit): array
    {
        $today = $this->clock->now()->modify(self::TODAY);
        $products = $this->reports->productSalesBetween(
            $today->modify(sprintf(self::DAYS_BACK_FORMAT, self::OUTLOOK_DAYS)),
            $today,
            $limit,
        );
        $outlook = [];

        foreach ($products as $product) {
            $outlook[] = new StockOutlook(
                $product->productId,
                $product->sku,
                $product->name,
                $product->available,
                $product->unitsSold,
                round($product->unitsSold / self::OUTLOOK_DAYS, self::PACE_DECIMALS),
                intdiv($product->available * self::OUTLOOK_DAYS, $product->unitsSold),
            );
        }

        return $outlook;
    }

    /** @return list<HeatmapCell> */
    public function orderHeatmap(ReportPeriod $period): array
    {
        return $this->reports->paidOrdersByWeekdayAndHour($period);
    }

    /**
     * The last twelve months, including the current one, each next to the same month a year
     * earlier; months without revenue are reported as zero.
     *
     * @return list<MonthlyRevenue>
     */
    public function monthlyRevenue(Currency $currency): array
    {
        $thisMonth = $this->clock->now()->modify(self::FIRST_DAY_OF_THIS_MONTH)->setTime(0, 0);
        $revenues = [];

        foreach ($this->reports->revenueByMonth(
            $currency,
            $thisMonth->modify(sprintf(self::MONTHS_BACK_FORMAT, self::MONTHS_WITH_PREVIOUS_YEAR - 1)),
            $thisMonth->modify(self::NEXT_MONTH),
        ) as $month) {
            $revenues[$month->month] = $month->revenue;
        }

        $months = [];
        for ($back = self::MONTHS - 1; $back >= 0; $back--) {
            $month = $thisMonth->modify(sprintf(self::MONTHS_BACK_FORMAT, $back));
            $revenue = $revenues[$month->format(self::MONTH_FORMAT)] ?? new Money(0, $currency);
            $previous = $revenues[$month->modify(self::YEAR_BACK)->format(self::MONTH_FORMAT)] ?? new Money(0, $currency);
            $months[] = new MonthlyRevenue($month->format(self::MONTH_FORMAT), $revenue, $previous, self::changePercent($revenue, $previous));
        }

        return $months;
    }

    /** @return list<ReportedOrder> */
    public function orders(OrderStatusEnum $status, DateTimeImmutable $since): array
    {
        return $this->reports->ordersByStatus($status, $since, self::ORDER_REPORT_LIMIT);
    }

    private static function changePercent(Money $revenue, Money $previous): ?float
    {
        if ($previous->isZero()) {
            return null;
        }

        return round((float) $revenue->subtract($previous)->ratioOf($previous) * self::PERCENT, self::CHANGE_DECIMALS);
    }
}
