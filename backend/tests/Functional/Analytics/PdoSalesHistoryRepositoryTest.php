<?php

declare(strict_types=1);

namespace Tests\Functional\Analytics;

use App\Analytics\Repository\PdoSalesHistoryRepository;
use App\Analytics\Service\SalesHistoryFactory;
use App\Analytics\ValueObject\HistoryOrder;
use App\Analytics\ValueObject\SalesDay;
use App\Shared\Database\DatabaseConnection;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoSalesHistoryRepositoryTest extends TestCase
{
    private const int PRODUCTS = 20;
    private const int ORDERS = 40;
    private const string DAY = '2025-11-28';
    private const int REMOVAL_BATCH = 15;
    private const string ORDERS_QUERY = <<<'SQL'
        SELECT orders.idempotency_key AS idempotencyKey, HEX(orders.public_id) AS publicId, orders.status,
            orders.total_amount AS total, orders.created_at AS placedAt, carts.status AS cartStatus,
            COUNT(order_items.product_id) AS lineCount,
            SUM(order_items.quantity * order_items.unit_price_amount) AS itemsTotal
        FROM orders
        JOIN carts ON carts.id = orders.cart_id
        JOIN order_items ON order_items.order_id = orders.id
        WHERE orders.idempotency_key LIKE 'history:%'
        GROUP BY orders.id
        ORDER BY orders.id
        SQL;
    private const string HISTORY_PRODUCTS_QUERY = "SELECT COUNT(*) FROM products WHERE sku LIKE 'history-%'";
    private const string ORPHAN_CARTS_QUERY = <<<'SQL'
        SELECT COUNT(*) FROM carts
        LEFT JOIN orders ON orders.cart_id = carts.id
        WHERE carts.status = 'checked_out' AND orders.id IS NULL
        SQL;

    private const string SIMULATION_PRODUCT_QUERY = "INSERT INTO products (sku, name, price_amount, price_currency, stock) VALUES ('bf-removal-test', 'Simulation product', 1000, 'PLN', 5)";
    private const string SIMULATION_CART_QUERY = "INSERT INTO carts (id, status) VALUES (:id, 'checked_out')";
    private const string SIMULATION_ORDER_QUERY = <<<'SQL'
        INSERT INTO orders (public_id, cart_id, idempotency_key, status, total_amount, total_currency, reservation_expires_at)
        VALUES (:public_id, :cart_id, CONCAT('simulation:removal-test:', UUID()), 'paid', 1000, 'PLN', NOW())
        SQL;
    private const string SIMULATION_LINE_QUERY = <<<'SQL'
        INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price_amount, unit_price_currency)
        VALUES (:order_id, :product_id, 'bf-removal-test', 'Simulation product', 1, 1000, 'PLN')
        SQL;
    private const string SIMULATION_PAYMENT_QUERY = <<<'SQL'
        INSERT INTO payments (public_id, order_id, provider, provider_payment_id, checkout_url, amount, currency, status, idempotency_key)
        VALUES (:public_id, :order_id, 'fakepay', CONCAT('fp_', UUID()), 'https://pay.example/fp', 1000, 'PLN', 'succeeded', CONCAT('simulation-payment:', UUID()))
        SQL;

    private PDO $pdo;
    private PdoSalesHistoryRepository $history;
    private SalesHistoryFactory $factory;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->history = new PdoSalesHistoryRepository($this->pdo);
        $this->factory = new SalesHistoryFactory();
        $this->removeAll();
    }

    protected function tearDown(): void
    {
        $this->removeAll();
    }

    public function testStoresOrdersWithTheirCartsLinesAndTotals(): void
    {
        self::assertFalse($this->history->hasHistory());

        $orders = $this->addDay();

        $stored = $this->rows(self::ORDERS_QUERY);
        self::assertTrue($this->history->hasHistory());
        self::assertSame(self::PRODUCTS, $this->countRows(self::HISTORY_PRODUCTS_QUERY));
        self::assertCount(self::ORDERS, $stored);

        foreach ($orders as $index => $order) {
            $row = $stored[$index];
            self::assertSame($order->idempotencyKey, $row['idempotencyKey']);
            self::assertSame(strtoupper(bin2hex($order->publicId)), $row['publicId']);
            self::assertSame($order->status->value, $row['status']);
            self::assertSame($order->placedAt->format('Y-m-d H:i:s'), $row['placedAt']);
            self::assertSame('checked_out', $row['cartStatus']);
            self::assertSame(count($order->lines), (int) $row['lineCount']);
            self::assertSame($order->total()->getAmount(), (string) $row['total']);
            self::assertSame((string) $row['total'], (string) $row['itemsTotal']);
        }
    }

    public function testRemovesHistoryInBatchesWithItsCartsAndProducts(): void
    {
        $this->addDay();

        self::assertSame(self::REMOVAL_BATCH, $this->history->removeBatch(self::REMOVAL_BATCH));
        $this->removeAll();

        self::assertFalse($this->history->hasHistory());
        self::assertSame(0, $this->countRows(self::HISTORY_PRODUCTS_QUERY));
        self::assertSame(0, $this->countRows(self::ORPHAN_CARTS_QUERY), 'No checked-out cart is left without its order.');
    }

    public function testAlsoRemovesSimulationOrdersWithTheirPaymentsAndProducts(): void
    {
        $this->pdo->prepare(self::SIMULATION_PRODUCT_QUERY)->execute();
        $productId = (int) $this->pdo->lastInsertId();
        $cartId = random_bytes(16);
        $this->pdo->prepare(self::SIMULATION_CART_QUERY)->execute(['id' => $cartId]);
        $this->pdo->prepare(self::SIMULATION_ORDER_QUERY)->execute(['public_id' => random_bytes(16), 'cart_id' => $cartId]);
        $orderId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(self::SIMULATION_LINE_QUERY)->execute(['order_id' => $orderId, 'product_id' => $productId]);
        $this->pdo->prepare(self::SIMULATION_PAYMENT_QUERY)->execute(['public_id' => random_bytes(16), 'order_id' => $orderId]);

        $this->removeAll();

        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM orders WHERE idempotency_key LIKE 'simulation:%'"));
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM products WHERE sku = 'bf-removal-test'"));
    }

    /** @return list<HistoryOrder> */
    private function addDay(): array
    {
        $products = $this->factory->products(self::PRODUCTS);
        $byId = array_combine($this->history->addProducts($products), $products);
        $orders = $this->factory->ordersForDay(new SalesDay(new DateTimeImmutable(self::DAY), self::ORDERS), 1, $byId);
        if ($orders === []) {
            self::fail('The factory made no orders.');
        }

        $this->history->addOrders($orders);

        return $orders;
    }

    /** @return list<array<string, string>> */
    private function rows(string $query): array
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute();
        /** @var list<array<string, string>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    private function countRows(string $query): int
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function removeAll(): void
    {
        while ($this->history->removeBatch(self::REMOVAL_BATCH) > 0) {
            continue;
        }

        $this->history->removeUnusedProducts();
    }
}
