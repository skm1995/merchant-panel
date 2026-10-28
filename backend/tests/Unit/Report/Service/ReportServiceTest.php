<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Service;

use App\Order\Enum\OrderStatusEnum;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Report\Service\ReportService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportServiceTest extends TestCase
{
    private const int LIMIT = 3;
    private const string SINCE = '2026-01-01';

    public function testAddsUpTheRevenueOfEachDayIntoARunningTotal(): void
    {
        $period = ReportFixtures::period();
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('dailySales')->willReturn([
            ReportFixtures::sales('2026-10-01', 2, 6000),
            ReportFixtures::sales('2026-10-03', 1, 2000),
            ReportFixtures::sales('2026-10-04', 4, 500),
        ]);

        self::assertEquals([
            ReportFixtures::day('2026-10-01', 2, 6000, 6000),
            ReportFixtures::day('2026-10-03', 1, 2000, 8000),
            ReportFixtures::day('2026-10-04', 4, 500, 8500),
        ], new ReportService($reports)->dailyRevenue($period));
    }

    public function testReportsNoDaysWhenNothingWasSold(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('dailySales')->willReturn([]);

        self::assertSame([], new ReportService($reports)->dailyRevenue(ReportFixtures::period()));
    }

    public function testRanksTheBestSellingProductsInTheOrderTheyCome(): void
    {
        $period = ReportFixtures::period();
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('bestSellingProducts')->willReturn([
            ReportFixtures::productSales(4, 2, 3000),
            ReportFixtures::productSales(9, 1, 3000),
            ReportFixtures::productSales(2, 1, 2000),
        ]);

        self::assertEquals([
            ReportFixtures::product(1, 4, 2, 3000),
            ReportFixtures::product(2, 9, 1, 3000),
            ReportFixtures::product(3, 2, 1, 2000),
        ], new ReportService($reports)->topProducts($period, self::LIMIT));
    }

    public function testLimitsTheOrderReport(): void
    {
        $since = new DateTimeImmutable(self::SINCE);
        $reports = $this->createMock(ReportReadRepositoryInterface::class);
        $reports->expects(self::once())->method('ordersByStatus')
            ->with(OrderStatusEnum::PAID, $since, ReportService::ORDER_REPORT_LIMIT)
            ->willReturn([]);

        self::assertSame([], new ReportService($reports)->orders(OrderStatusEnum::PAID, $since));
    }
}
