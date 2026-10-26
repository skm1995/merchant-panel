<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;
use App\Shared\Database\RecordFetcher;
use App\Shared\Database\SqlPlaceholders;
use PDO;

final class PdoProductRepository implements ProductRepositoryInterface
{
    private const string COLUMNS = 'id, sku, name, price_amount AS priceAmount, price_currency AS priceCurrency, stock, reserved, version';
    private const string FIND_QUERY = 'SELECT ' . self::COLUMNS . ' FROM products WHERE id = :id';
    private const string PAGE_QUERY = 'SELECT ' . self::COLUMNS . ' FROM products WHERE id > :after_id ORDER BY id LIMIT :limit';
    private const string LOCK_QUERY_FORMAT = 'SELECT ' . self::COLUMNS . ' FROM products WHERE id IN (%s) ORDER BY id FOR UPDATE';
    private const string RESERVE_QUERY = <<<'SQL'
        UPDATE products
        SET reserved = reserved + :quantity, version = version + 1
        WHERE id = :id AND stock - reserved >= :required_quantity
        SQL;
    private const string RELEASE_QUERY = <<<'SQL'
        UPDATE products
        SET reserved = reserved - :quantity, version = version + 1
        WHERE id = :id AND reserved >= :required_quantity
        SQL;
    private const string SELL_RESERVED_QUERY = <<<'SQL'
        UPDATE products
        SET stock = stock - :quantity, reserved = reserved - :quantity_reserved, version = version + 1
        WHERE id = :id AND reserved >= :required_quantity
        SQL;
    private const string QUANTITY_RESERVED_PARAMETER = 'quantity_reserved';
    private const string QUANTITY_PARAMETER = 'quantity';
    private const string REQUIRED_QUANTITY_PARAMETER = 'required_quantity';
    private const string ID_PARAMETER = 'id';
    private const string PRICE_AMOUNT_PARAMETER = 'price_amount';
    private const string PRICE_CURRENCY_PARAMETER = 'price_currency';
    private const string STOCK_PARAMETER = 'stock';
    private const string STOCK_FLOOR_PARAMETER = 'stock_floor';
    private const string EXPECTED_VERSION_PARAMETER = 'expected_version';
    private const string AFTER_ID_PARAMETER = 'after_id';
    private const string LIMIT_PARAMETER = 'limit';
    private const int FIRST_PAGE_AFTER_ID = 0;
    private const string UPDATE_QUERY = <<<'SQL'
        UPDATE products
        SET price_amount = :price_amount, price_currency = :price_currency, stock = :stock, version = version + 1
        WHERE id = :id AND version = :expected_version AND reserved <= :stock_floor
        SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Product
    {
        $statement = $this->pdo->prepare(self::FIND_QUERY);
        $statement->execute([self::ID_PARAMETER => $id]);

        return RecordFetcher::one($statement, ProductRecord::class)?->toEntity();
    }

    public function findPage(int $limit, ?int $afterId): array
    {
        $statement = $this->pdo->prepare(self::PAGE_QUERY);
        $statement->bindValue(self::AFTER_ID_PARAMETER, $afterId ?? self::FIRST_PAGE_AFTER_ID, PDO::PARAM_INT);
        $statement->bindValue(self::LIMIT_PARAMETER, $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (ProductRecord $record): Product => $record->toEntity(),
            RecordFetcher::all($statement, ProductRecord::class),
        );
    }

    public function lockForUpdate(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $statement = $this->pdo->prepare(sprintf(self::LOCK_QUERY_FORMAT, SqlPlaceholders::positional(count($ids))));
        $statement->execute($ids);

        return array_map(
            static fn (ProductRecord $record): Product => $record->toEntity(),
            RecordFetcher::all($statement, ProductRecord::class),
        );
    }

    public function reserve(int $productId, int $quantity): bool
    {
        return $this->changeReservation(self::RESERVE_QUERY, $productId, $quantity);
    }

    public function release(int $productId, int $quantity): bool
    {
        return $this->changeReservation(self::RELEASE_QUERY, $productId, $quantity);
    }

    public function sellReserved(int $productId, int $quantity): bool
    {
        $statement = $this->pdo->prepare(self::SELL_RESERVED_QUERY);
        $statement->execute([
            self::ID_PARAMETER => $productId,
            self::QUANTITY_PARAMETER => $quantity,
            self::QUANTITY_RESERVED_PARAMETER => $quantity,
            self::REQUIRED_QUANTITY_PARAMETER => $quantity,
        ]);

        return $statement->rowCount() === 1;
    }

    public function update(Product $product, int $expectedVersion): bool
    {
        $statement = $this->pdo->prepare(self::UPDATE_QUERY);
        $statement->execute([
            self::ID_PARAMETER => $product->id,
            self::PRICE_AMOUNT_PARAMETER => $product->price->getAmount(),
            self::PRICE_CURRENCY_PARAMETER => $product->price->getCurrency()->getCode(),
            self::STOCK_PARAMETER => $product->stock,
            self::STOCK_FLOOR_PARAMETER => $product->stock,
            self::EXPECTED_VERSION_PARAMETER => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    private function changeReservation(string $query, int $productId, int $quantity): bool
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            self::ID_PARAMETER => $productId,
            self::QUANTITY_PARAMETER => $quantity,
            self::REQUIRED_QUANTITY_PARAMETER => $quantity,
        ]);

        return $statement->rowCount() === 1;
    }
}
