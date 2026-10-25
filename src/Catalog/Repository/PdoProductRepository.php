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
}
