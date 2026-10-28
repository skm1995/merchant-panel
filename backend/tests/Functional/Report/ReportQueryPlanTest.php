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
    private const string REPORTING_INDEX = 'orders_status_created_at_totals';
    private const string RANGE_ACCESS = 'range';
    private const string LOOKUP_BY_PRIMARY_KEY = 'eq_ref';
    private const string SUMMARY_TABLE = 'daily_product_sales';

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
        $orders = $this->tableAccess(PdoReportReadRepository::DAILY_REVENUE_QUERY, PdoReportReadRepository::periodParameters(self::period()), 'orders');

        self::assertSame(self::REPORTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
        self::assertTrue($orders['using_index'] ?? false, 'The index also holds the currency and total, so no order row is read.');
    }

    public function testTopProductsRangeScansTheSummaryAndLooksProductsUpByPrimaryKey(): void
    {
        $parameters = PdoReportReadRepository::summaryPeriodParameters(self::period()) + ['limit' => 10];
        $summary = $this->tableAccess(PdoReportReadRepository::TOP_PRODUCTS_QUERY, $parameters, self::SUMMARY_TABLE);
        $products = $this->tableAccess(PdoReportReadRepository::TOP_PRODUCTS_QUERY, $parameters, 'products');

        self::assertSame(self::PRIMARY_KEY, $summary['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $summary['access_type'] ?? null);
        self::assertSame(self::PRIMARY_KEY, $products['key'] ?? null);
        self::assertSame(self::LOOKUP_BY_PRIMARY_KEY, $products['access_type'] ?? null);
    }

    public function testOrderHeatmapReadsOnlyTheReportingIndex(): void
    {
        $orders = $this->tableAccess(PdoReportReadRepository::ORDER_HEATMAP_QUERY, PdoReportReadRepository::orderRangeParameters(self::period()), 'orders');

        self::assertSame(self::REPORTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
        self::assertTrue($orders['using_index'] ?? false, 'The index holds every column the query needs, so no order row is read.');
    }

    public function testStockOutlookRangeScansTheSummaryAndLooksProductsUpByPrimaryKey(): void
    {
        $parameters = ['starts_at' => '2002-03-01', 'ends_before' => '2002-03-29', 'limit' => 10];
        $summary = $this->tableAccess(PdoReportReadRepository::STOCK_OUTLOOK_QUERY, $parameters, self::SUMMARY_TABLE);
        $products = $this->tableAccess(PdoReportReadRepository::STOCK_OUTLOOK_QUERY, $parameters, 'products');

        self::assertSame(self::PRIMARY_KEY, $summary['key'] ?? null, 'The summary key starts with the day.');
        self::assertSame(self::RANGE_ACCESS, $summary['access_type'] ?? null);
        self::assertSame(self::PRIMARY_KEY, $products['key'] ?? null);
        self::assertSame(self::LOOKUP_BY_PRIMARY_KEY, $products['access_type'] ?? null);
    }

    public function testMonthlyRevenueRangeScansTheSummary(): void
    {
        $summary = $this->tableAccess(
            PdoReportReadRepository::MONTHLY_REVENUE_QUERY,
            PdoReportReadRepository::monthlyRevenueParameters(new Currency('PLN'), new DateTimeImmutable('2002-03-01'), new DateTimeImmutable('2002-05-01')),
            self::SUMMARY_TABLE,
        );

        self::assertSame(self::PRIMARY_KEY, $summary['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $summary['access_type'] ?? null);
    }

    private static function period(): ReportPeriod
    {
        return new ReportPeriod(new DateTimeImmutable('2002-03-01'), new DateTimeImmutable('2002-03-07'), new Currency('PLN'));
    }

    /**
     * @param array<string, int|string> $parameters
     * @return array<string, mixed>
     */
    private function tableAccess(string $query, array $parameters, string $table): array
    {
        return QueryPlan::tableAccess($this->pdo, $query, $parameters, $table);
    }
}
