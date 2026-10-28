<?php

declare(strict_types=1);

namespace App\Analytics\Repository;

use App\Analytics\ValueObject\HistoryLine;
use App\Analytics\ValueObject\HistoryOrder;
use App\Analytics\ValueObject\HistoryProduct;
use App\Shared\Database\SqlDateTime;
use App\Shared\Database\SqlPlaceholders;
use PDO;

/**
 * Stores the sales history with multi-row INSERTs, a thousand rows per statement instead of
 * a statement per row, and removes it in batches. Every query here is a plain INSERT,
 * SELECT, or DELETE; LIKE 'history:%' uses the unique index on the idempotency key.
 */
final class PdoSalesHistoryRepository implements SalesHistoryRepositoryInterface
{
    private const string HAS_HISTORY_QUERY = "SELECT 1 FROM orders WHERE idempotency_key LIKE 'history:%' LIMIT 1";
    private const string INSERT_PRODUCTS_QUERY_FORMAT = 'INSERT INTO products (sku, name, price_amount, price_currency, stock) VALUES %s';
    private const string PRODUCT_IDS_QUERY_FORMAT = 'SELECT sku, id FROM products WHERE sku IN (%s)';
    private const string INSERT_CARTS_QUERY_FORMAT = "INSERT INTO carts (id, status, created_at) VALUES %s";
    private const string INSERT_ORDERS_QUERY_FORMAT = <<<'SQL'
        INSERT INTO orders (public_id, cart_id, idempotency_key, status, version, total_amount, total_currency,
            reservation_expires_at, created_at, updated_at)
        VALUES %s
        SQL;
    private const string ORDER_IDS_QUERY_FORMAT = 'SELECT idempotency_key, id FROM orders WHERE idempotency_key IN (%s)';
    private const string INSERT_LINES_QUERY_FORMAT = <<<'SQL'
        INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price_amount, unit_price_currency)
        VALUES %s
        SQL;
    private const string BATCH_QUERY = "SELECT id, cart_id FROM orders WHERE idempotency_key LIKE 'history:%' LIMIT :limit";
    private const string DELETE_ORDERS_QUERY_FORMAT = 'DELETE FROM orders WHERE id IN (%s)';
    private const string DELETE_CARTS_QUERY_FORMAT = 'DELETE FROM carts WHERE id IN (%s)';
    private const string DELETE_UNUSED_PRODUCTS_QUERY = <<<'SQL'
        DELETE products FROM products
        LEFT JOIN order_items ON order_items.product_id = products.id
        LEFT JOIN cart_items ON cart_items.product_id = products.id
        WHERE products.sku LIKE 'history-%' AND order_items.product_id IS NULL AND cart_items.product_id IS NULL
        SQL;
    private const string RECLAIM_SPACE_QUERY = 'OPTIMIZE TABLE order_items, orders, carts, products';
    private const string CHECKED_OUT = 'checked_out';
    private const string RESERVATION_WINDOW = '+15 minutes';
    private const string LAST_CHANGE = '+10 minutes';
    private const int PRODUCT_COLUMNS = 5;
    private const int CART_COLUMNS = 3;
    private const int ORDER_COLUMNS = 10;
    private const int LINE_COLUMNS = 7;
    private const int VERSION_AFTER_LAST_CHANGE = 2;
    private const string ID_KEY = 'id';
    private const string CART_ID_KEY = 'cart_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function hasHistory(): bool
    {
        $statement = $this->pdo->prepare(self::HAS_HISTORY_QUERY);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function addProducts(array $products): array
    {
        $values = [];
        foreach ($products as $product) {
            array_push($values, $product->sku, $product->name, $product->price->getAmount(), $product->price->getCurrency()->getCode(), $product->stock);
        }

        $this->insert(self::INSERT_PRODUCTS_QUERY_FORMAT, count($products), self::PRODUCT_COLUMNS, $values);
        $skus = array_map(static fn (HistoryProduct $product): string => $product->sku, $products);
        $ids = $this->idsByKey(self::PRODUCT_IDS_QUERY_FORMAT, $skus);

        return array_map(static fn (string $sku): int => $ids[$sku], $skus);
    }

    public function addOrders(array $orders): void
    {
        $carts = [];
        $rows = [];
        foreach ($orders as $order) {
            $placedAt = SqlDateTime::format($order->placedAt);
            array_push($carts, $order->cartId, self::CHECKED_OUT, $placedAt);
            array_push(
                $rows,
                $order->publicId,
                $order->cartId,
                $order->idempotencyKey,
                $order->status->value,
                self::VERSION_AFTER_LAST_CHANGE,
                $order->total()->getAmount(),
                $order->total()->getCurrency()->getCode(),
                SqlDateTime::format($order->placedAt->modify(self::RESERVATION_WINDOW)),
                $placedAt,
                SqlDateTime::format($order->placedAt->modify(self::LAST_CHANGE)),
            );
        }

        $this->insert(self::INSERT_CARTS_QUERY_FORMAT, count($orders), self::CART_COLUMNS, $carts);
        $this->insert(self::INSERT_ORDERS_QUERY_FORMAT, count($orders), self::ORDER_COLUMNS, $rows);
        $orderIds = $this->idsByKey(
            self::ORDER_IDS_QUERY_FORMAT,
            array_map(static fn (HistoryOrder $order): string => $order->idempotencyKey, $orders),
        );

        $lines = [];
        $lineCount = 0;
        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                array_push($lines, $orderIds[$order->idempotencyKey], ...self::lineValues($line));
                $lineCount++;
            }
        }

        $this->insert(self::INSERT_LINES_QUERY_FORMAT, $lineCount, self::LINE_COLUMNS, $lines);
    }

    public function removeBatch(int $limit): int
    {
        $statement = $this->pdo->prepare(self::BATCH_QUERY);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $batch = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($batch === []) {
            return 0;
        }

        $placeholders = SqlPlaceholders::positional(count($batch));
        $this->pdo->prepare(sprintf(self::DELETE_ORDERS_QUERY_FORMAT, $placeholders))->execute(array_column($batch, self::ID_KEY));
        $this->pdo->prepare(sprintf(self::DELETE_CARTS_QUERY_FORMAT, $placeholders))->execute(array_column($batch, self::CART_ID_KEY));

        return count($batch);
    }

    public function removeUnusedProducts(): int
    {
        return (int) $this->pdo->exec(self::DELETE_UNUSED_PRODUCTS_QUERY);
    }

    public function reclaimSpace(): void
    {
        // OPTIMIZE TABLE reports its result as rows, which must be read before the next query.
        $statement = $this->pdo->prepare(self::RECLAIM_SPACE_QUERY);
        $statement->execute();
        $statement->fetchAll();
    }

    /** @return list<int|string> */
    private static function lineValues(HistoryLine $line): array
    {
        return [
            $line->productId,
            $line->sku,
            $line->name,
            $line->quantity,
            $line->unitPrice->getAmount(),
            $line->unitPrice->getCurrency()->getCode(),
        ];
    }

    /** @param list<int|string> $values */
    private function insert(string $queryFormat, int $rowCount, int $columnCount, array $values): void
    {
        $this->pdo->prepare(sprintf($queryFormat, SqlPlaceholders::rows($rowCount, $columnCount)))->execute($values);
    }

    /**
     * @param list<string> $keys
     * @return array<string, int> ids keyed by the unique value they were looked up by
     */
    private function idsByKey(string $queryFormat, array $keys): array
    {
        $statement = $this->pdo->prepare(sprintf($queryFormat, SqlPlaceholders::positional(count($keys))));
        $statement->execute($keys);

        return array_map(intval(...), $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
