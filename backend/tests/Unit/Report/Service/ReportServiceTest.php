<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Service;

use App\Order\Enum\OrderStatusEnum;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Report\Service\ReportService;
use DateTimeImmutable;
use Money\Currency;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;
use Tests\Unit\Support\FrozenClock;

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
        ], $this->service($reports)->dailyRevenue($period));
    }

    public function testReportsNoDaysWhenNothingWasSold(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('dailySales')->willReturn([]);

        self::assertSame([], $this->service($reports)->dailyRevenue(ReportFixtures::period()));
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
        ], $this->service($reports)->topProducts($period, self::LIMIT));
    }

    public function testMeasuresEachProductsPaceAndTheDaysItsAvailableStockLasts(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('productSalesBetween')->willReturn([
            ReportFixtures::pace(4, 6, 56),
            ReportFixtures::pace(5, 0, 10),
            ReportFixtures::pace(6, 30, 28),
            ReportFixtures::pace(7, 100, 3),
        ]);

        $outlook = $this->service($reports)->stockOutlook(self::LIMIT);

        self::assertSame(
            [[4, 2.0, 3], [5, 0.36, 0], [6, 1.0, 30], [7, 0.11, 933]],
            array_map(static fn ($product): array => [$product->productId, $product->unitsPerDay, $product->daysLeft], $outlook),
            'Days left are whole days at the exact pace, not at the rounded one: 100 units at 3 per 28 days last 933 days.',
        );
    }

    public function testComparesTheLastTwelveMonthsWithTheSameMonthsAYearEarlier(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('revenueByMonth')->willReturn([
            ReportFixtures::monthRevenue('2025-01', 500),
            ReportFixtures::monthRevenue('2025-02', 2000),
            ReportFixtures::monthRevenue('2025-09', 1000),
            ReportFixtures::monthRevenue('2026-02', 3000),
        ]);

        $months = $this->service($reports, '2026-02-15 10:00:00')->monthlyRevenue(new Currency(ReportFixtures::CURRENCY));

        self::assertCount(12, $months);
        self::assertSame('2025-03', $months[0]->month);
        self::assertSame(
            ['2025-09' => ['1000', '0', null], '2026-01' => ['0', '500', -100.0], '2026-02' => ['3000', '2000', 50.0]],
            array_map(
                static fn ($month): array => [$month->revenue->getAmount(), $month->previousYearRevenue->getAmount(), $month->changePercent],
                array_intersect_key(array_column($months, null, 'month'), array_flip(['2025-09', '2026-01', '2026-02'])),
            ),
        );
        self::assertSame(['0', '0', null], [$months[1]->revenue->getAmount(), $months[1]->previousYearRevenue->getAmount(), $months[1]->changePercent]);
    }

    public function testRoundsTheChangeToOneDecimalPlace(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('revenueByMonth')->willReturn([
            ReportFixtures::monthRevenue('2025-02', 3000),
            ReportFixtures::monthRevenue('2026-02', 1000),
        ]);

        $months = $this->service($reports, '2026-02-15 10:00:00')->monthlyRevenue(new Currency(ReportFixtures::CURRENCY));

        self::assertSame(-66.7, $months[11]->changePercent);
    }

    public function testLimitsTheOrderReport(): void
    {
        $since = new DateTimeImmutable(self::SINCE);
        $reports = $this->createMock(ReportReadRepositoryInterface::class);
        $reports->expects(self::once())->method('ordersByStatus')
            ->with(OrderStatusEnum::PAID, $since, ReportService::ORDER_REPORT_LIMIT)
            ->willReturn([]);

        self::assertSame([], $this->service($reports)->orders(OrderStatusEnum::PAID, $since));
    }

    private function service(ReportReadRepositoryInterface $reports, string $now = '2026-10-28 10:00:00'): ReportService
    {
        return new ReportService($reports, new FrozenClock($now));
    }
}
