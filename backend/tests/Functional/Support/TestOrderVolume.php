<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use PDO;

/**
 * Inserts a realistic volume of orders (3,000 over a year, with items) and refreshes the table
 * statistics, so EXPLAIN shows the plan MySQL uses on real data. delete() removes it again.
 */
final readonly class TestOrderVolume
{
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
    private const string DELETE_CARTS = <<<'SQL'
        DELETE FROM carts WHERE id IN (
            WITH RECURSIVE sequence (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM sequence WHERE n < 3000)
            SELECT UNHEX(MD5(CONCAT(:cart_seed, n))) FROM sequence
        )
        SQL;
    private const string ALLOW_DEEP_RECURSION = 'SET SESSION cte_max_recursion_depth = 5000';
    private const string ANALYZE = 'ANALYZE TABLE orders, order_items';
    private const string KEY_PREFIX = 'plan-';
    private const string PRODUCT_PREFIX = 'plan-product-';

    private function __construct(private PDO $pdo, private string $seed, private string $productPrefix)
    {
    }

    public static function generate(PDO $pdo): self
    {
        $seed = uniqid(self::KEY_PREFIX, true);
        $pdo->exec(self::ALLOW_DEEP_RECURSION);
        $pdo->prepare(self::GENERATE_CARTS)->execute(['cart_seed' => $seed]);
        $pdo->prepare(self::GENERATE_ORDERS_FOR_CARTS)->execute([
            'public_seed' => $seed . 'o',
            'cart_seed' => $seed,
            'key_prefix' => $seed,
        ]);
        $productPrefix = TestProductData::uniquePrefix(self::PRODUCT_PREFIX);
        [$productId] = TestProductData::create($pdo, $productPrefix, 1, 1000, 1000);
        $pdo->prepare(self::GENERATE_ORDER_ITEMS)->execute([
            'product_id' => $productId,
            'key_pattern' => $seed . '%',
        ]);
        $analyze = $pdo->prepare(self::ANALYZE);
        $analyze->execute();
        $analyze->fetchAll();

        return new self($pdo, $seed, $productPrefix);
    }

    public function delete(): void
    {
        $this->pdo->prepare(self::DELETE_ORDERS)->execute(['key_pattern' => $this->seed . '%']);
        $this->pdo->prepare(self::DELETE_CARTS)->execute(['cart_seed' => $this->seed]);
        TestProductData::delete($this->pdo, $this->productPrefix);
    }
}
