<?php

declare(strict_types=1);

namespace Tests\Functional\Report;

use App\Cart\Entity\CartId;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestOrderData;
use Tests\Functional\Support\TestProductData;

/**
 * The stock outlook, order heatmap, and monthly revenue reports through the API key. Each
 * report reads data no other test writes: summary rows of this test's own products, in CHF
 * for the monthly revenue, and orders in August 2001 for the heatmap.
 */
final class SalesInsightsApiTest extends TestCase
{
    private const string SKU_PREFIX = 'insights-';
    private const string STOCK_OUTLOOK_PATH = '/api/v1/reports/stock-outlook';
    private const string ORDER_HEATMAP_PATH = '/api/v1/reports/order-heatmap';
    private const string MONTHLY_REVENUE_PATH = '/api/v1/reports/monthly-revenue';
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';
    private const string API_KEY_HEADER = 'X-Api-Key';
    private const string PAID = 'paid';
    private const string CANCELLED = 'cancelled';
    private const string CURRENCY = 'CHF';
    private const string INSERT_SUMMARY_QUERY = <<<'SQL'
        INSERT INTO daily_product_sales (day, product_id, currency, units, revenue)
        VALUES (:day, :product_id, :currency, :units, :revenue)
        SQL;
    private const string RESERVE_QUERY = 'UPDATE products SET reserved = :reserved WHERE id = :id';
    private const string DATE_FORMAT = 'Y-m-d';
    private const string MONTH_FORMAT = 'Y-m';

    private PDO $pdo;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testListsTheProductsThatSellOutFirstAtTheRecentPace(): void
    {
        [$slow, $fast] = TestProductData::create($this->pdo, $this->prefix, 2, 1000, 30);
        $this->pdo->prepare(self::RESERVE_QUERY)->execute(['reserved' => 24, 'id' => $fast]);
        $this->summary($this->daysAgo(1), $slow, 20);
        $this->summary($this->daysAgo(10), $slow, 8);
        $this->summary($this->daysAgo(1), $fast, 56);
        $this->summary($this->daysAgo(30), $fast, 900);

        $report = $this->report(self::STOCK_OUTLOOK_PATH, ['limit' => '50']);
        $ours = array_values(array_filter(
            $report['products'],
            static fn (array $product): bool => in_array($product['product_id'], [$slow, $fast], true),
        ));

        self::assertSame(28, $report['days_measured']);
        self::assertSame([
            ['product_id' => $fast, 'available' => 6, 'units_sold' => 56, 'units_per_day' => 2.0, 'days_left' => 3],
            ['product_id' => $slow, 'available' => 30, 'units_sold' => 28, 'units_per_day' => 1.0, 'days_left' => 30],
        ], array_map(static fn (array $product): array => [
            'product_id' => $product['product_id'],
            'available' => $product['available'],
            'units_sold' => $product['units_sold'],
            'units_per_day' => $product['units_per_day'],
            'days_left' => $product['days_left'],
        ], $ours), 'Sales older than 28 days do not count, and the fastest to sell out comes first.');
    }

    public function testCountsPaidOrdersPerWeekdayAndHour(): void
    {
        [$product] = TestProductData::create($this->pdo, $this->prefix, 1, 1000, 100);
        $this->order(self::PAID, '2001-08-06 09:15:00', $product);
        $this->order(self::PAID, '2001-08-06 09:45:00', $product);
        $this->order(self::CANCELLED, '2001-08-06 09:30:00', $product);
        $this->order(self::PAID, '2001-08-12 18:00:00', $product);

        $report = $this->report(self::ORDER_HEATMAP_PATH, ['from' => '2001-08-01', 'to' => '2001-08-31']);

        self::assertSame([
            ['weekday' => 1, 'hour' => 9, 'orders' => 2],
            ['weekday' => 7, 'hour' => 18, 'orders' => 1],
        ], $report['cells'], 'Monday is 1 and Sunday 7; cancelled orders do not count.');
    }

    public function testComparesEachOfTheLastTwelveMonthsWithTheYearBefore(): void
    {
        [$product] = TestProductData::create($this->pdo, $this->prefix, 1, 1000, 100);
        $thisMonth = new DateTimeImmutable('first day of this month');
        $this->summary($thisMonth, $product, 3, 3000, self::CURRENCY);
        $this->summary($thisMonth->modify('-1 year'), $product, 2, 2000, self::CURRENCY);
        $this->summary($thisMonth->modify('-5 months'), $product, 1, 1000, self::CURRENCY);

        $report = $this->report(self::MONTHLY_REVENUE_PATH, ['currency' => self::CURRENCY]);
        $months = array_column($report['months'], null, 'month');

        self::assertSame(self::CURRENCY, $report['currency']);
        self::assertCount(12, $report['months']);
        self::assertSame($thisMonth->format(self::MONTH_FORMAT), $report['months'][11]['month']);
        self::assertSame(['3000', '2000', 50.0], $this->monthValues($months[$thisMonth->format(self::MONTH_FORMAT)]));
        self::assertSame(['1000', '0', null], $this->monthValues($months[$thisMonth->modify('-5 months')->format(self::MONTH_FORMAT)]));
        self::assertSame(['0', '0', null], $this->monthValues($months[$thisMonth->modify('-1 month')->format(self::MONTH_FORMAT)]));
    }

    public function testRequiresTheReportsApiKey(): void
    {
        foreach ([self::STOCK_OUTLOOK_PATH, self::ORDER_HEATMAP_PATH, self::MONTHLY_REVENUE_PATH] as $path) {
            $response = ApiClient::get($path, ['from' => '2001-08-01', 'to' => '2001-08-31']);

            self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $response->getStatusCode(), $path);
            ApiClient::assertMatchesSpecification($path, HttpMethodEnum::GET->value, $response);
        }
    }

    private function daysAgo(int $days): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('today -%d days', $days));
    }

    private function summary(DateTimeImmutable $day, int $productId, int $units, int $revenue = 1000, string $currency = 'PLN'): void
    {
        $this->pdo->prepare(self::INSERT_SUMMARY_QUERY)->execute([
            'day' => $day->format(self::DATE_FORMAT),
            'product_id' => $productId,
            'currency' => $currency,
            'units' => $units,
            'revenue' => $revenue,
        ]);
    }

    private function order(string $status, string $createdAt, int $productId): void
    {
        $this->cartIds[] = TestOrderData::insert($this->pdo, $status, $createdAt, [[$productId, 1, 1000]]);
    }

    /**
     * @param array<string, mixed> $month
     * @return array{mixed, mixed, mixed}
     */
    private function monthValues(array $month): array
    {
        self::assertIsArray($month['revenue']);
        self::assertIsArray($month['previous_year_revenue']);

        return [$month['revenue']['amount'], $month['previous_year_revenue']['amount'], $month['change_percent']];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function report(string $path, array $query): array
    {
        $response = ApiClient::sendWithQuery($path, $query, [self::API_KEY_HEADER => Environment::get(self::API_KEY_VARIABLE)]);

        self::assertSame(ResponseStatusEnum::OK->value, $response->getStatusCode(), (string) $response->getBody());
        ApiClient::assertMatchesSpecification($path, HttpMethodEnum::GET->value, $response);

        return ApiClient::json($response);
    }
}
