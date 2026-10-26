<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use PDO;

final class TestProductData
{
    private const string INSERT_QUERY = 'INSERT INTO products (sku, name, price_amount, price_currency, stock, reserved) VALUES (:sku, :name, :price_amount, :price_currency, :stock, :reserved)';
    private const string DELETE_QUERY = 'DELETE FROM products WHERE sku LIKE :prefix';
    private const string SKU_FORMAT = '%s-%d';
    private const string LIKE_PREFIX_FORMAT = '%s%%';
    private const string NAME_FORMAT = 'Test product %d';
    private const string CURRENCY = 'PLN';
    private const int RANDOM_SUFFIX_BYTES = 6;

    public static function uniquePrefix(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(self::RANDOM_SUFFIX_BYTES));
    }

    /**
     * Inserts products whose SKUs start with the prefix and returns their ids in insertion order.
     *
     * @return list<int>
     */
    public static function create(PDO $pdo, string $prefix, int $count, int $priceInMinorUnits, int $stock, int $reserved = 0): array
    {
        $ids = [];
        $statement = $pdo->prepare(self::INSERT_QUERY);

        for ($number = 1; $number <= $count; $number++) {
            $statement->execute([
                'sku' => sprintf(self::SKU_FORMAT, $prefix, $number),
                'name' => sprintf(self::NAME_FORMAT, $number),
                'price_amount' => $priceInMinorUnits,
                'price_currency' => self::CURRENCY,
                'stock' => $stock,
                'reserved' => $reserved,
            ]);
            $ids[] = (int) $pdo->lastInsertId();
        }

        return $ids;
    }

    public static function delete(PDO $pdo, string $prefix): void
    {
        $pdo->prepare(self::DELETE_QUERY)->execute(['prefix' => sprintf(self::LIKE_PREFIX_FORMAT, $prefix)]);
    }
}
