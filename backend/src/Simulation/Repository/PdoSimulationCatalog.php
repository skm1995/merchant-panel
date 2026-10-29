<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Shared\Database\RecordFetcher;
use App\Shared\Database\SqlPlaceholders;
use App\Simulation\ReadModel\SimulationProduct;
use DateTimeImmutable;
use PDO;

final readonly class PdoSimulationCatalog implements SimulationCatalogInterface
{
    // An existing product keeps what open orders reserved; only its available stock is reset.
    private const string PREPARE_QUERY = <<<'SQL'
        INSERT INTO products (sku, name, price_amount, price_currency, stock, reserved)
        VALUES (:sku, :name, :price_amount, 'PLN', :stock, 0) AS new
        ON DUPLICATE KEY UPDATE stock = products.reserved + new.stock
        SQL;
    private const string PRODUCTS_QUERY = <<<'SQL'
        SELECT id AS productId, sku, name, stock, reserved
        FROM products
        WHERE sku LIKE 'bf-%'
        ORDER BY sku
        SQL;
    private const string PAID_UNITS_QUERY = <<<'SQL'
        SELECT COALESCE(SUM(order_items.quantity), 0)
        FROM orders
        JOIN order_items ON order_items.order_id = orders.id
        WHERE orders.idempotency_key LIKE :key_prefix AND orders.status = :status
        SQL;
    private const string PAID_DAYS_QUERY = <<<'SQL'
        SELECT DISTINCT DATE(created_at)
        FROM orders
        WHERE idempotency_key LIKE :key_prefix AND status = :status
        SQL;
    private const string FINISHED_ORDERS_QUERY = <<<'SQL'
        SELECT id
        FROM orders
        WHERE idempotency_key LIKE :key_prefix AND status <> :status
        SQL;
    // A simulation cart without an order: its order was removed, or its buyer found no stock.
    private const string CARTS_WITHOUT_ORDERS_QUERY = <<<'SQL'
        SELECT cart_items.cart_id
        FROM cart_items
        JOIN products ON products.id = cart_items.product_id
        LEFT JOIN orders ON orders.cart_id = cart_items.cart_id
        WHERE products.sku LIKE 'bf-%' AND orders.id IS NULL
        GROUP BY cart_items.cart_id
        SQL;
    private const string DELETE_PAYMENTS_QUERY_FORMAT = 'DELETE FROM payments WHERE order_id IN (%s)';
    private const string DELETE_ORDERS_QUERY_FORMAT = 'DELETE FROM orders WHERE id IN (%s)';
    private const string DELETE_CARTS_QUERY_FORMAT = 'DELETE FROM carts WHERE id IN (%s)';
    private const string ALL_RUNS_KEY_PREFIX = 'simulation:%';
    private const int DELETE_BATCH_SIZE = 1000;
    private const string SKU_FORMAT = 'bf-%d';
    private const string KEY_PREFIX_FORMAT = 'simulation:%s:%%';
    private const string SKU_PARAMETER = 'sku';
    private const string NAME_PARAMETER = 'name';
    private const string PRICE_PARAMETER = 'price_amount';
    private const string STOCK_PARAMETER = 'stock';
    private const string KEY_PREFIX_PARAMETER = 'key_prefix';
    private const string STATUS_PARAMETER = 'status';

    /** @var list<array{string, int}> names and prices in grosze */
    private const array PRODUCTS = [
        ['Black Friday TV', 199900],
        ['Black Friday Headphones', 29900],
        ['Black Friday Coffee Machine', 89900],
        ['Black Friday Phone', 249900],
        ['Black Friday Vacuum', 119900],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function prepareProducts(int $count, int $availableStock): array
    {
        $statement = $this->pdo->prepare(self::PREPARE_QUERY);

        foreach (array_slice(self::PRODUCTS, 0, $count) as $index => [$name, $price]) {
            $statement->execute([
                self::SKU_PARAMETER => sprintf(self::SKU_FORMAT, $index + 1),
                self::NAME_PARAMETER => $name,
                self::PRICE_PARAMETER => $price,
                self::STOCK_PARAMETER => $availableStock,
            ]);
        }

        return $this->currentProducts();
    }

    public function currentProducts(): array
    {
        $statement = $this->pdo->prepare(self::PRODUCTS_QUERY);
        $statement->execute();

        return array_map(
            static fn (SimulationProductRecord $record): SimulationProduct => $record->toEntity(),
            RecordFetcher::all($statement, SimulationProductRecord::class),
        );
    }

    public function paidUnits(string $simulationId): int
    {
        $statement = $this->pdo->prepare(self::PAID_UNITS_QUERY);
        $statement->execute([
            self::KEY_PREFIX_PARAMETER => sprintf(self::KEY_PREFIX_FORMAT, $simulationId),
            self::STATUS_PARAMETER => OrderStatusEnum::PAID->value,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function paidOrderDays(): array
    {
        $statement = $this->pdo->prepare(self::PAID_DAYS_QUERY);
        $statement->execute([
            self::KEY_PREFIX_PARAMETER => self::ALL_RUNS_KEY_PREFIX,
            self::STATUS_PARAMETER => OrderStatusEnum::PAID->value,
        ]);

        /** @var list<string> $days */
        $days = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_map(static fn (string $day): DateTimeImmutable => new DateTimeImmutable($day), $days);
    }

    public function removeFinishedOrders(): void
    {
        $orders = $this->pdo->prepare(self::FINISHED_ORDERS_QUERY);
        $orders->execute([
            self::KEY_PREFIX_PARAMETER => self::ALL_RUNS_KEY_PREFIX,
            self::STATUS_PARAMETER => OrderStatusEnum::PENDING_PAYMENT->value,
        ]);
        /** @var list<int> $orderIds */
        $orderIds = $orders->fetchAll(PDO::FETCH_COLUMN);

        // A payment keeps its order, and an order keeps its cart, so they go in this order.
        $this->deleteInBatches(self::DELETE_PAYMENTS_QUERY_FORMAT, $orderIds);
        $this->deleteInBatches(self::DELETE_ORDERS_QUERY_FORMAT, $orderIds);

        $carts = $this->pdo->prepare(self::CARTS_WITHOUT_ORDERS_QUERY);
        $carts->execute();
        /** @var list<int> $cartIds */
        $cartIds = $carts->fetchAll(PDO::FETCH_COLUMN);
        $this->deleteInBatches(self::DELETE_CARTS_QUERY_FORMAT, $cartIds);
    }

    /** @param list<int> $ids */
    private function deleteInBatches(string $queryFormat, array $ids): void
    {
        foreach (array_chunk($ids, self::DELETE_BATCH_SIZE) as $batch) {
            $this->pdo->prepare(sprintf($queryFormat, SqlPlaceholders::positional(count($batch))))->execute($batch);
        }
    }
}
