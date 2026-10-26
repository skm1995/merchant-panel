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

    public function testReadsRevenueAndTopProductsForThePeriod(): void
    {
        $period = ReportFixtures::period();
        $days = [ReportFixtures::day(ReportFixtures::FROM, 1, 100, 100)];
        $products = [ReportFixtures::product(1, 7, 1, 100)];
        $reports = $this->createMock(ReportReadRepositoryInterface::class);
        $reports->expects(self::once())->method('dailyRevenue')->with($period)->willReturn($days);
        $reports->expects(self::once())->method('topProducts')->with($period, self::LIMIT)->willReturn($products);
        $service = new ReportService($reports);

        self::assertSame($days, $service->dailyRevenue($period));
        self::assertSame($products, $service->topProducts($period, self::LIMIT));
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
