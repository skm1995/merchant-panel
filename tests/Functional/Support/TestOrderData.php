<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Cart\Entity\CartId;
use App\Order\Entity\OrderId;
use PDO;

/**
 * Inserts settled orders directly, for reports that need orders at chosen dates.
 */
final class TestOrderData
{
    private const string INSERT_CART_QUERY = "INSERT INTO carts (id, status) VALUES (:id, 'checked_out')";
    private const string INSERT_ORDER_QUERY = <<<'SQL'
        INSERT INTO orders (public_id, cart_id, idempotency_key, status, total_amount, total_currency, reservation_expires_at, created_at)
        VALUES (:public_id, :cart_id, :idempotency_key, :status, :total_amount, 'PLN', :created_at, :created_at_value)
        SQL;
    private const string INSERT_ITEM_QUERY = <<<'SQL'
        INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price_amount, unit_price_currency)
        VALUES (:order_id, :product_id, :sku, :name, :quantity, :unit_price_amount, 'PLN')
        SQL;
    private const string KEY_PREFIX = 'test-order-';

    /**
     * @param list<array{int, int, int}> $lines product id, quantity, and unit price in minor units
     */
    public static function insert(PDO $pdo, string $status, string $createdAt, array $lines): CartId
    {
        $cartId = CartId::generate();
        $pdo->prepare(self::INSERT_CART_QUERY)->execute(['id' => $cartId->toBinary()]);
        $pdo->prepare(self::INSERT_ORDER_QUERY)->execute([
            'public_id' => OrderId::generate()->toBinary(),
            'cart_id' => $cartId->toBinary(),
            'idempotency_key' => uniqid(self::KEY_PREFIX, true),
            'status' => $status,
            'total_amount' => array_sum(array_map(static fn (array $line): int => $line[1] * $line[2], $lines)),
            'created_at' => $createdAt,
            'created_at_value' => $createdAt,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $insertItem = $pdo->prepare(self::INSERT_ITEM_QUERY);

        foreach ($lines as [$productId, $quantity, $unitPrice]) {
            $insertItem->execute([
                'order_id' => $orderId,
                'product_id' => $productId,
                'sku' => 'SKU-' . $productId,
                'name' => 'Product ' . $productId,
                'quantity' => $quantity,
                'unit_price_amount' => $unitPrice,
            ]);
        }

        return $cartId;
    }
}
