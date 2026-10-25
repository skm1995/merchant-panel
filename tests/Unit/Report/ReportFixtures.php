<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use App\Report\Query\ReportPeriod;
use App\Report\ReadModel\DailyRevenue;
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

    public static function product(int $rank, int $productId, int $unitsSold, int $revenue): TopProduct
    {
        return new TopProduct($rank, $productId, 'SKU-' . $productId, 'Product ' . $productId, $unitsSold, Money::PLN($revenue));
    }
}
