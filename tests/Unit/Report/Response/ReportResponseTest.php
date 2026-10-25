<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Response;

use App\Report\Response\DailyRevenueResponse;
use App\Report\Response\TopProductsResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportResponseTest extends TestCase
{
    public function testDescribesDailyRevenueWithRunningTotals(): void
    {
        $body = DailyRevenueResponse::fromRows(ReportFixtures::period(), [
            ReportFixtures::day('2026-10-01', 2, 3000, 3000),
            ReportFixtures::day('2026-10-03', 1, 500, 3500),
        ])->toArray();

        self::assertSame(['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, 'currency' => 'PLN'], array_slice($body, 0, 3));
        self::assertSame([
            'day' => '2026-10-03',
            'orders' => 1,
            'revenue' => ['amount' => '500', 'currency' => 'PLN'],
            'running_total' => ['amount' => '3500', 'currency' => 'PLN'],
        ], $body['days'][1]);
    }

    public function testDescribesRankedProducts(): void
    {
        $body = TopProductsResponse::fromRows(ReportFixtures::period(), [ReportFixtures::product(1, 7, 4, 8000)])->toArray();

        self::assertSame([
            'rank' => 1,
            'product_id' => 7,
            'sku' => 'SKU-7',
            'name' => 'Product 7',
            'units_sold' => 4,
            'revenue' => ['amount' => '8000', 'currency' => 'PLN'],
        ], $body['products'][0]);
    }
}
