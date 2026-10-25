<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Cart\Entity\CartId;
use PDO;

/**
 * Deletes test carts together with the orders and payments that came from them.
 */
final class TestCartData
{
    private const string DELETE_PAYMENTS_QUERY = 'DELETE payments FROM payments JOIN orders ON orders.id = payments.order_id WHERE orders.cart_id = :id';
    private const string DELETE_ORDERS_QUERY = 'DELETE FROM orders WHERE cart_id = :id';
    private const string DELETE_QUERY = 'DELETE FROM carts WHERE id = :id';

    /** @param list<CartId> $ids */
    public static function delete(PDO $pdo, array $ids): void
    {
        $deletePayments = $pdo->prepare(self::DELETE_PAYMENTS_QUERY);
        $deleteOrders = $pdo->prepare(self::DELETE_ORDERS_QUERY);
        $deleteCart = $pdo->prepare(self::DELETE_QUERY);

        foreach ($ids as $id) {
            $deletePayments->execute(['id' => $id->toBinary()]);
            $deleteOrders->execute(['id' => $id->toBinary()]);
            $deleteCart->execute(['id' => $id->toBinary()]);
        }
    }
}
