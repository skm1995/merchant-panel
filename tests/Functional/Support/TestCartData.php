<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Cart\Entity\CartId;
use PDO;

/**
 * Deletes test carts together with the orders placed from them.
 */
final class TestCartData
{
    private const string DELETE_ORDERS_QUERY = 'DELETE FROM orders WHERE cart_id = :id';
    private const string DELETE_QUERY = 'DELETE FROM carts WHERE id = :id';

    /** @param list<CartId> $ids */
    public static function delete(PDO $pdo, array $ids): void
    {
        $deleteOrders = $pdo->prepare(self::DELETE_ORDERS_QUERY);
        $deleteCart = $pdo->prepare(self::DELETE_QUERY);

        foreach ($ids as $id) {
            $deleteOrders->execute(['id' => $id->toBinary()]);
            $deleteCart->execute(['id' => $id->toBinary()]);
        }
    }
}
