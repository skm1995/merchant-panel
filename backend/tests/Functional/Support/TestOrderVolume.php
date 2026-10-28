<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Shared\Database\SqlPlaceholders;
use DateTimeImmutable;
use PDO;

/**
 * Inserts a realistic volume of data dated in 2002 and refreshes the table statistics, so
 * EXPLAIN shows the plan MySQL uses on real data: 3,000 orders over the year with their items,
 * and a daily sales summary of 20 products for every day of it. delete() removes it again.
 */
final readonly class TestOrderVolume
{
    private const string INSERT_CARTS_FORMAT = "INSERT INTO carts (id, status) VALUES %s";
    private const string INSERT_ORDERS_FORMAT = <<<'SQL'
        INSERT INTO orders (public_id, cart_id, idempotency_key, status, total_amount, total_currency, reservation_expires_at, created_at)
        VALUES %s
        SQL;
    private const string INSERT_ORDER_ITEMS = <<<'SQL'
        INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price_amount, unit_price_currency)
        SELECT orders.id, :product_id, 'PLAN', 'Plan product', 1 + orders.id % 3, 1000, 'PLN'
        FROM orders
        WHERE orders.idempotency_key LIKE :key_pattern
        SQL;
    private const string INSERT_SUMMARY_FORMAT = 'INSERT INTO daily_product_sales (day, product_id, currency, units, revenue) VALUES %s';
    private const string DELETE_ORDERS = 'DELETE FROM orders WHERE idempotency_key LIKE :key_pattern';
    private const string DELETE_CARTS_FORMAT = 'DELETE FROM carts WHERE id IN (%s)';
    private const string ANALYZE = 'ANALYZE TABLE orders, order_items, daily_product_sales';
    private const string KEY_PREFIX = 'plan-';
    private const string PRODUCT_PREFIX = 'plan-product-';
    private const string KEY_PATTERN_FORMAT = '%s%%';
    private const string FIRST_DAY = '2002-01-01';
    private const string DAY_FORMAT = '+%d days';
    private const string DATE_FORMAT = 'Y-m-d';
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private const string CHECKED_OUT = 'checked_out';
    private const string PAID = 'paid';
    private const string EXPIRED = 'expired';
    private const string CURRENCY = 'PLN';
    private const int ORDERS = 3000;
    private const int DAYS = 365;
    private const int SUMMARY_PRODUCTS = 20;
    private const int BATCH = 1000;
    private const int UUID_BYTES = 16;

    /** @param list<string> $cartIds */
    private function __construct(private PDO $pdo, private string $keyPrefix, private string $productPrefix, private array $cartIds)
    {
    }

    public static function generate(PDO $pdo): self
    {
        $keyPrefix = uniqid(self::KEY_PREFIX, true);
        $productPrefix = TestProductData::uniquePrefix(self::PRODUCT_PREFIX);
        $productIds = TestProductData::create($pdo, $productPrefix, self::SUMMARY_PRODUCTS, 1000, 1000);
        $cartIds = [];
        $orders = [];

        for ($number = 1; $number <= self::ORDERS; $number++) {
            $cartId = random_bytes(self::UUID_BYTES);
            $placedAt = self::day($number % self::DAYS)->format(self::DATE_TIME_FORMAT);
            $cartIds[] = $cartId;
            $orders[] = [
                random_bytes(self::UUID_BYTES), $cartId, $keyPrefix . $number,
                $number % 10 === 0 ? self::PAID : self::EXPIRED, 1000 + $number, self::CURRENCY, $placedAt, $placedAt,
            ];
        }

        self::insertRows($pdo, self::INSERT_CARTS_FORMAT, array_map(static fn (string $id): array => [$id, self::CHECKED_OUT], $cartIds));
        self::insertRows($pdo, self::INSERT_ORDERS_FORMAT, $orders);
        $pdo->prepare(self::INSERT_ORDER_ITEMS)->execute([
            'product_id' => $productIds[0],
            'key_pattern' => sprintf(self::KEY_PATTERN_FORMAT, $keyPrefix),
        ]);

        $summary = [];
        for ($day = 0; $day < self::DAYS; $day++) {
            foreach ($productIds as $productId) {
                $summary[] = [self::day($day)->format(self::DATE_FORMAT), $productId, self::CURRENCY, 1 + $day % 5, 1000 * (1 + $day % 5)];
            }
        }
        self::insertRows($pdo, self::INSERT_SUMMARY_FORMAT, $summary);

        $analyze = $pdo->prepare(self::ANALYZE);
        $analyze->execute();
        $analyze->fetchAll();

        return new self($pdo, $keyPrefix, $productPrefix, $cartIds);
    }

    public function delete(): void
    {
        $this->pdo->prepare(self::DELETE_ORDERS)->execute(['key_pattern' => sprintf(self::KEY_PATTERN_FORMAT, $this->keyPrefix)]);

        foreach (array_chunk($this->cartIds, self::BATCH) as $batch) {
            $this->pdo->prepare(sprintf(self::DELETE_CARTS_FORMAT, SqlPlaceholders::positional(count($batch))))->execute($batch);
        }

        TestProductData::delete($this->pdo, $this->productPrefix);
    }

    private static function day(int $offset): DateTimeImmutable
    {
        return new DateTimeImmutable(self::FIRST_DAY)->modify(sprintf(self::DAY_FORMAT, $offset));
    }

    /**
     * Inserts the rows a thousand at a time, one multi-row INSERT per batch.
     *
     * @param list<list<int|string>> $rows
     */
    private static function insertRows(PDO $pdo, string $queryFormat, array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH) as $batch) {
            $pdo->prepare(sprintf($queryFormat, SqlPlaceholders::rows(count($batch), count($batch[0]))))
                ->execute(array_merge(...$batch));
        }
    }
}
