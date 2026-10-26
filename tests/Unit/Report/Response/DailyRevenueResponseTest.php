<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Response;

use App\Report\Response\DailyRevenueResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class DailyRevenueResponseTest extends TestCase
{
    public function testDescribesDailyRevenueWithRunningTotals(): void
    {
        $body = new DailyRevenueResponse(ReportFixtures::period(), [
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
}
