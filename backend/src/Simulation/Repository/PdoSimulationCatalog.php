<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Shared\Database\RecordFetcher;
use App\Simulation\ReadModel\SimulationProduct;
use PDO;

final readonly class PdoSimulationCatalog implements SimulationCatalogInterface
{
    // An existing product keeps what open orders reserved; only its available stock is reset.
    private const string PREPARE_QUERY = <<<'SQL'
        INSERT INTO products (sku, name, price_amount, price_currency, stock, reserved)
        VALUES (:sku, :name, :price_amount, 'PLN', :stock, 0) AS new
        ON DUPLICATE KEY UPDATE stock = products.reserved + new.stock
        SQL;
    private const string PRODUCTS_QUERY = <<<'SQL'
        SELECT id AS productId, sku, name, stock, reserved
        FROM products
        WHERE sku LIKE 'bf-%'
        ORDER BY sku
        SQL;
    private const string PAID_UNITS_QUERY = <<<'SQL'
        SELECT COALESCE(SUM(order_items.quantity), 0)
        FROM orders
        JOIN order_items ON order_items.order_id = orders.id
        WHERE orders.idempotency_key LIKE :key_prefix AND orders.status = :status
        SQL;
    private const string SKU_FORMAT = 'bf-%d';
    private const string KEY_PREFIX_FORMAT = 'simulation:%s:%%';
    private const string SKU_PARAMETER = 'sku';
    private const string NAME_PARAMETER = 'name';
    private const string PRICE_PARAMETER = 'price_amount';
    private const string STOCK_PARAMETER = 'stock';
    private const string KEY_PREFIX_PARAMETER = 'key_prefix';
    private const string STATUS_PARAMETER = 'status';

    /** @var list<array{string, int}> names and prices in grosze */
    private const array PRODUCTS = [
        ['Black Friday TV', 199900],
        ['Black Friday Headphones', 29900],
        ['Black Friday Coffee Machine', 89900],
        ['Black Friday Phone', 249900],
        ['Black Friday Vacuum', 119900],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function prepareProducts(int $count, int $availableStock): array
    {
        $statement = $this->pdo->prepare(self::PREPARE_QUERY);

        foreach (array_slice(self::PRODUCTS, 0, $count) as $index => [$name, $price]) {
            $statement->execute([
                self::SKU_PARAMETER => sprintf(self::SKU_FORMAT, $index + 1),
                self::NAME_PARAMETER => $name,
                self::PRICE_PARAMETER => $price,
                self::STOCK_PARAMETER => $availableStock,
            ]);
        }

        return $this->currentProducts();
    }

    public function currentProducts(): array
    {
        $statement = $this->pdo->prepare(self::PRODUCTS_QUERY);
        $statement->execute();

        return array_map(
            static fn (SimulationProductRecord $record): SimulationProduct => $record->toEntity(),
            RecordFetcher::all($statement, SimulationProductRecord::class),
        );
    }

    public function paidUnits(string $simulationId): int
    {
        $statement = $this->pdo->prepare(self::PAID_UNITS_QUERY);
        $statement->execute([
            self::KEY_PREFIX_PARAMETER => sprintf(self::KEY_PREFIX_FORMAT, $simulationId),
            self::STATUS_PARAMETER => OrderStatusEnum::PAID->value,
        ]);

        return (int) $statement->fetchColumn();
    }
}
