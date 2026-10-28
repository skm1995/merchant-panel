<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use App\Report\ValueObject\ReportPeriod;
use App\Report\ReadModel\DailyRevenue;
use App\Report\ReadModel\DailySales;
use App\Report\ReadModel\MonthRevenue;
use App\Report\ReadModel\ProductPace;
use App\Report\ReadModel\ProductSales;
use App\Report\ReadModel\TopProduct;
use DateTimeImmutable;
use Money\Currency;
use Money\Money;

final class ReportFixtures
{
    public const string FROM = '2026-10-01';
    public const string TO = '2026-10-07';
    public const string CURRENCY = 'PLN';

    public static function period(): ReportPeriod
    {
        return new ReportPeriod(new DateTimeImmutable(self::FROM), new DateTimeImmutable(self::TO), new Currency(self::CURRENCY));
    }

    public static function day(string $day, int $orders, int $revenue, int $runningTotal): DailyRevenue
    {
        return new DailyRevenue(new DateTimeImmutable($day), $orders, Money::PLN($revenue), Money::PLN($runningTotal));
    }

    public static function sales(string $day, int $orders, int $revenue): DailySales
    {
        return new DailySales(new DateTimeImmutable($day), $orders, Money::PLN($revenue));
    }

    public static function productSales(int $productId, int $unitsSold, int $revenue): ProductSales
    {
        return new ProductSales($productId, 'SKU-' . $productId, 'Product ' . $productId, $unitsSold, Money::PLN($revenue));
    }

    public static function pace(int $productId, int $available, int $unitsSold): ProductPace
    {
        return new ProductPace($productId, 'SKU-' . $productId, 'Product ' . $productId, $available, $unitsSold);
    }

    public static function monthRevenue(string $month, int $revenue): MonthRevenue
    {
        return new MonthRevenue($month, Money::PLN($revenue));
    }

    public static function product(int $rank, int $productId, int $unitsSold, int $revenue): TopProduct
    {
        return new TopProduct($rank, $productId, 'SKU-' . $productId, 'Product ' . $productId, $unitsSold, Money::PLN($revenue));
    }
}
