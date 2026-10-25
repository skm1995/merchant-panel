<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Cart\Entity\CartId;
use PDO;

final class TestCartData
{
    private const string DELETE_QUERY = 'DELETE FROM carts WHERE id = :id';

    /** @param list<CartId> $ids */
    public static function delete(PDO $pdo, array $ids): void
    {
        $statement = $pdo->prepare(self::DELETE_QUERY);

        foreach ($ids as $id) {
            $statement->execute(['id' => $id->toBinary()]);
        }
    }
}
