<?php

declare(strict_types=1);

namespace Tests\Functional\Report;

use App\Report\Service\ReportPeriod;
use App\Report\Repository\PdoReportReadRepository;
use App\Shared\Database\DatabaseConnection;
use DateTimeImmutable;
use Money\Currency;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestProductData;

/**
 * Loads a realistic number of orders, refreshes the optimizer statistics, and checks that
 * the report queries range-scan the reporting index instead of scanning the whole table.
 */
final class ReportQueryPlanTest extends TestCase
{
    private const string KEY_PREFIX = 'plan-';
    private const string GENERATE_CARTS = <<<'SQL'
        INSERT INTO carts (id, status)
        WITH RECURSIVE sequence (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM sequence WHERE n < 3000)
        SELECT UNHEX(MD5(CONCAT(:cart_seed, n))), 'checked_out' FROM sequence;
        SQL;
    private const string GENERATE_ORDERS_FOR_CARTS = <<<'SQL'
        INSERT INTO orders (public_id, cart_id, idempotency_key, status, total_amount, total_currency, reservation_expires_at, created_at)
        WITH RECURSIVE sequence (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM sequence WHERE n < 3000)
        SELECT UNHEX(MD5(CONCAT(:public_seed, n))), UNHEX(MD5(CONCAT(:cart_seed, n))), CONCAT(:key_prefix, n),
            IF(n % 10 = 0, 'paid', 'expired'), 1000 + n, 'PLN',
            '2002-01-01' + INTERVAL n % 365 DAY, '2002-01-01' + INTERVAL n % 365 DAY
        FROM sequence
        SQL;
    private const string GENERATE_ORDER_ITEMS = <<<'SQL'
        INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price_amount, unit_price_currency)
        SELECT orders.id, :product_id, 'PLAN', 'Plan product', 1 + orders.id % 3, 1000, 'PLN'
        FROM orders
        WHERE orders.idempotency_key LIKE :key_pattern
        SQL;
    private const string DELETE_ORDERS = 'DELETE FROM orders WHERE idempotency_key LIKE :key_pattern';
    private const string PRIMARY_KEY = 'PRIMARY';
    private const string PRODUCT_PREFIX = 'plan-product-';
    private const string DELETE_CARTS = <<<'SQL'
        DELETE FROM carts WHERE id IN (
            WITH RECURSIVE sequence (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM sequence WHERE n < 3000)
            SELECT UNHEX(MD5(CONCAT(:cart_seed, n))) FROM sequence
        )
        SQL;
    private const string ALLOW_DEEP_RECURSION = 'SET SESSION cte_max_recursion_depth = 5000';
    private const string ANALYZE = 'ANALYZE TABLE orders, order_items';
    private const string EXPLAIN_FORMAT = 'EXPLAIN FORMAT=JSON %s';
    private const string REPORTING_INDEX = 'orders_status_created_at';
    private const string RANGE_ACCESS = 'range';

    private PDO $pdo;
    private string $seed;
    private string $productPrefix;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->seed = uniqid(self::KEY_PREFIX, true);
        $this->pdo->exec(self::ALLOW_DEEP_RECURSION);
        $this->pdo->prepare(self::GENERATE_CARTS)->execute(['cart_seed' => $this->seed]);
        $this->pdo->prepare(self::GENERATE_ORDERS_FOR_CARTS)->execute([
            'public_seed' => $this->seed . 'o',
            'cart_seed' => $this->seed,
            'key_prefix' => $this->seed,
        ]);
        $this->productPrefix = TestProductData::uniquePrefix(self::PRODUCT_PREFIX);
        [$productId] = TestProductData::create($this->pdo, $this->productPrefix, 1, 1000, 1000);
        $this->pdo->prepare(self::GENERATE_ORDER_ITEMS)->execute([
            'product_id' => $productId,
            'key_pattern' => $this->seed . '%',
        ]);
        $analyze = $this->pdo->prepare(self::ANALYZE);
        $analyze->execute();
        $analyze->fetchAll();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare(self::DELETE_ORDERS)->execute(['key_pattern' => $this->seed . '%']);
        $this->pdo->prepare(self::DELETE_CARTS)->execute(['cart_seed' => $this->seed]);
        TestProductData::delete($this->pdo, $this->productPrefix);
    }

    public function testDailyRevenueRangeScansTheReportingIndex(): void
    {
        $orders = $this->ordersAccess(PdoReportReadRepository::DAILY_REVENUE_QUERY, []);

        self::assertSame(self::REPORTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
    }

    public function testTopProductsRangeScansOrdersAndJoinsItemsByPrimaryKey(): void
    {
        $plan = $this->plan(PdoReportReadRepository::TOP_PRODUCTS_QUERY, ['limit' => 10]);
        $orders = self::findTable($plan, 'orders');
        $items = self::findTable($plan, 'order_items');

        self::assertSame(self::REPORTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
        self::assertSame(self::PRIMARY_KEY, $items['key'] ?? null, 'Items are looked up by order_id, the primary key prefix.');
    }

    /**
     * @param array<string, int> $extraParameters
     * @return array<string, mixed> the plan node of the orders table
     */
    private function ordersAccess(string $query, array $extraParameters): array
    {
        $node = self::findTable($this->plan($query, $extraParameters), 'orders');
        self::assertNotNull($node, 'The plan does not read the orders table.');

        return $node;
    }

    /**
     * @param array<string, int> $extraParameters
     * @return array<mixed>
     */
    private function plan(string $query, array $extraParameters): array
    {
        $period = new ReportPeriod(new DateTimeImmutable('2002-03-01'), new DateTimeImmutable('2002-03-07'), new Currency('PLN'));
        $statement = $this->pdo->prepare(sprintf(self::EXPLAIN_FORMAT, $query));

        foreach (PdoReportReadRepository::periodParameters($period) + $extraParameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->execute();
        $plan = json_decode((string) $statement->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($plan);

        return $plan;
    }

    /**
     * @param array<mixed> $plan
     * @return array<string, mixed>|null
     */
    private static function findTable(array $plan, string $table): ?array
    {
        if (($plan['table_name'] ?? null) === $table) {
            /** @var array<string, mixed> $plan */
            return $plan;
        }

        foreach ($plan as $child) {
            $found = is_array($child) ? self::findTable($child, $table) : null;

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
