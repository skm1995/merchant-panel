<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;
use App\Shared\Database\RecordFetcher;
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
    private const string COMMIT_QUERY = <<<'SQL'
        UPDATE products
        SET stock = stock - :quantity, reserved = reserved - :quantity_reserved, version = version + 1
        WHERE id = :id AND reserved >= :required_quantity
        SQL;
    private const string QUANTITY_RESERVED_PARAMETER = 'quantity_reserved';
    private const string POSITIONAL_PLACEHOLDER = '?';
    private const string PLACEHOLDER_SEPARATOR = ', ';
    private const string QUANTITY_PARAMETER = 'quantity';
    private const string REQUIRED_QUANTITY_PARAMETER = 'required_quantity';
    private const string ID_PARAMETER = 'id';
    private const string AFTER_ID_PARAMETER = 'after_id';
    private const string LIMIT_PARAMETER = 'limit';
    private const int FIRST_PAGE_AFTER_ID = 0;

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

        $placeholders = implode(self::PLACEHOLDER_SEPARATOR, array_fill(0, count($ids), self::POSITIONAL_PLACEHOLDER));
        $statement = $this->pdo->prepare(sprintf(self::LOCK_QUERY_FORMAT, $placeholders));
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

    public function commit(int $productId, int $quantity): bool
    {
        $statement = $this->pdo->prepare(self::COMMIT_QUERY);
        $statement->execute([
            self::ID_PARAMETER => $productId,
            self::QUANTITY_PARAMETER => $quantity,
            self::QUANTITY_RESERVED_PARAMETER => $quantity,
            self::REQUIRED_QUANTITY_PARAMETER => $quantity,
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
