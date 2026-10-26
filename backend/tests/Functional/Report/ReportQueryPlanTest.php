<?php

declare(strict_types=1);

namespace Tests\Functional\Report;

use App\Report\ValueObject\ReportPeriod;
use App\Report\Repository\PdoReportReadRepository;
use App\Shared\Database\DatabaseConnection;
use DateTimeImmutable;
use Money\Currency;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\QueryPlan;
use Tests\Functional\Support\TestOrderVolume;

/**
 * Loads a realistic number of orders, refreshes the optimizer statistics, and checks that
 * the report queries range-scan the reporting index instead of scanning the whole table.
 */
final class ReportQueryPlanTest extends TestCase
{
    private const string PRIMARY_KEY = 'PRIMARY';
    private const string REPORTING_INDEX = 'orders_status_created_at';
    private const string RANGE_ACCESS = 'range';

    private PDO $pdo;
    private TestOrderVolume $volume;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->volume = TestOrderVolume::generate($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->volume->delete();
    }

    public function testDailyRevenueRangeScansTheReportingIndex(): void
    {
        $orders = $this->tableAccess(PdoReportReadRepository::DAILY_REVENUE_QUERY, [], 'orders');

        self::assertSame(self::REPORTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
    }

    public function testTopProductsRangeScansOrdersAndJoinsItemsByPrimaryKey(): void
    {
        $orders = $this->tableAccess(PdoReportReadRepository::TOP_PRODUCTS_QUERY, ['limit' => 10], 'orders');
        $items = $this->tableAccess(PdoReportReadRepository::TOP_PRODUCTS_QUERY, ['limit' => 10], 'order_items');

        self::assertSame(self::REPORTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
        self::assertSame(self::PRIMARY_KEY, $items['key'] ?? null, 'Items are looked up by order_id, the primary key prefix.');
    }

    /**
     * @param array<string, int> $extraParameters
     * @return array<string, mixed>
     */
    private function tableAccess(string $query, array $extraParameters, string $table): array
    {
        $period = new ReportPeriod(new DateTimeImmutable('2002-03-01'), new DateTimeImmutable('2002-03-07'), new Currency('PLN'));

        return QueryPlan::tableAccess($this->pdo, $query, PdoReportReadRepository::periodParameters($period) + $extraParameters, $table);
    }
}
