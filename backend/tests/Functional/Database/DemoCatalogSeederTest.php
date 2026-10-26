<?php

declare(strict_types=1);

namespace Tests\Functional\Database;

use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestProductData;

final class DemoCatalogSeederTest extends TestCase
{
    private const string SEED_COMMAND = 'composer seed-demo --quiet 2>&1';
    private const string DEMO_SKU_PREFIX = 'demo-';
    private const string COUNT_QUERY = 'SELECT COUNT(*) FROM products WHERE sku LIKE :prefix';
    private const string FIRST_SKU_QUERY = 'SELECT sku FROM products WHERE sku LIKE :prefix ORDER BY sku LIMIT 1';
    private const string STOCK_QUERY = 'SELECT stock FROM products WHERE sku = :sku';
    private const string UPDATE_STOCK_QUERY = 'UPDATE products SET stock = :stock WHERE sku = :sku';
    private const string LIKE_PREFIX = 'demo-%';
    private const int EDITED_STOCK = 7;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        TestProductData::delete($this->pdo, self::DEMO_SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestProductData::delete($this->pdo, self::DEMO_SKU_PREFIX);
    }

    public function testSeedsTheDemoCatalogOnceAndKeepsEditedProducts(): void
    {
        $this->seed();
        $seeded = $this->demoProductCount();
        $sku = $this->firstDemoSku();
        $this->pdo->prepare(self::UPDATE_STOCK_QUERY)->execute(['stock' => self::EDITED_STOCK, 'sku' => $sku]);

        $this->seed();

        self::assertGreaterThan(0, $seeded);
        self::assertSame($seeded, $this->demoProductCount());
        self::assertSame(self::EDITED_STOCK, $this->stock($sku));
    }

    private function seed(): void
    {
        exec(self::SEED_COMMAND, $output, $status);

        self::assertSame(0, $status, implode(PHP_EOL, $output));
    }

    private function demoProductCount(): int
    {
        $statement = $this->pdo->prepare(self::COUNT_QUERY);
        $statement->execute(['prefix' => self::LIKE_PREFIX]);

        return (int) $statement->fetchColumn();
    }

    private function firstDemoSku(): string
    {
        $statement = $this->pdo->prepare(self::FIRST_SKU_QUERY);
        $statement->execute(['prefix' => self::LIKE_PREFIX]);

        return (string) $statement->fetchColumn();
    }

    private function stock(string $sku): int
    {
        $statement = $this->pdo->prepare(self::STOCK_QUERY);
        $statement->execute(['sku' => $sku]);

        return (int) $statement->fetchColumn();
    }
}
