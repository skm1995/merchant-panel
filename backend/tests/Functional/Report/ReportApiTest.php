<?php

declare(strict_types=1);

namespace Tests\Functional\Report;

use App\Cart\Entity\CartId;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestOrderData;
use Tests\Functional\Support\TestProductData;

/**
 * Uses orders dated in 2001, a range no other test writes to, so the totals are exact.
 */
final class ReportApiTest extends TestCase
{
    private const string SKU_PREFIX = 'report-';
    private const string DAILY_REVENUE_PATH = '/api/v1/reports/daily-revenue';
    private const string TOP_PRODUCTS_PATH = '/api/v1/reports/top-products';
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';
    private const string API_KEY_HEADER = 'X-Api-Key';
    private const string PAID = 'paid';
    private const string CANCELLED = 'cancelled';
    private const array PERIOD = ['from' => '2001-03-01', 'to' => '2001-03-31'];

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

    public function testReportsDailyRevenueWithRunningTotalAndRanksProducts(): void
    {
        [$mug, $plate, $bowl] = TestProductData::create($this->pdo, $this->prefix, 3, 100, 100);
        $this->order(self::PAID, '2001-03-02 09:00:00', [[$mug, 2, 1500]]);
        $this->order(self::PAID, '2001-03-02 18:30:00', [[$plate, 1, 2000], [$bowl, 1, 1000]]);
        $this->order(self::PAID, '2001-03-05 12:00:00', [[$bowl, 2, 1000]]);
        $this->order(self::CANCELLED, '2001-03-05 13:00:00', [[$mug, 10, 1500]]);
        $this->order(self::PAID, '2001-04-01 00:00:00', [[$mug, 1, 1500]]);

        $daily = $this->report(self::DAILY_REVENUE_PATH, self::PERIOD);
        $top = $this->report(self::TOP_PRODUCTS_PATH, self::PERIOD + ['limit' => '2']);

        self::assertSame([
            ['day' => '2001-03-02', 'orders' => 2, 'revenue' => '6000', 'running_total' => '6000'],
            ['day' => '2001-03-05', 'orders' => 1, 'revenue' => '2000', 'running_total' => '8000'],
        ], array_map(static fn (array $day): array => [
            'day' => $day['day'],
            'orders' => $day['orders'],
            'revenue' => $day['revenue']['amount'],
            'running_total' => $day['running_total']['amount'],
        ], $daily['days']));
        self::assertSame(
            [[1, $mug, '3000'], [1, $bowl, '3000'], [2, $plate, '2000']],
            array_map(static fn (array $product): array => [$product['rank'], $product['product_id'], $product['revenue']['amount']], $top['products']),
            'Ties share a rank, so the top two ranks can hold three products.',
        );
    }

    public function testRequiresTheReportsApiKey(): void
    {
        $response = ApiClient::get(self::DAILY_REVENUE_PATH, self::PERIOD);

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $response->getStatusCode());
        ApiClient::assertMatchesSpecification(self::DAILY_REVENUE_PATH, HttpMethodEnum::GET->value, $response);
    }

    /** @param list<array{int, int, int}> $lines */
    private function order(string $status, string $createdAt, array $lines): void
    {
        $this->cartIds[] = TestOrderData::insert($this->pdo, $status, $createdAt, $lines);
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function report(string $path, array $query): array
    {
        $response = ApiClient::sendWithQuery($path, $query, [self::API_KEY_HEADER => Environment::get(self::API_KEY_VARIABLE)]);

        self::assertSame(ResponseStatusEnum::OK->value, $response->getStatusCode());
        ApiClient::assertMatchesSpecification($path, HttpMethodEnum::GET->value, $response);

        return ApiClient::json($response);
    }
}
