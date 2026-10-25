<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog;

use App\Catalog\Repository\PdoProductRepository;
use App\Shared\Database\DatabaseConnection;
use Money\Money;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestProductData;

final class PdoProductRepositoryTest extends TestCase
{
    private const string SKU_PREFIX = 'repository-';
    private const string OVER_RESERVE_QUERY = 'UPDATE products SET reserved = stock + 1 WHERE id = :id';
    private const int PRICE_IN_GROSZE = 1999;
    private const int STOCK = 5;
    private const int RESERVED = 2;

    private PDO $pdo;
    private PdoProductRepository $products;
    private string $prefix;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->products = new PdoProductRepository($this->pdo);
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testMapsPersistedProductToEntityWithMoney(): void
    {
        [$id] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK, self::RESERVED);

        $product = $this->products->findById($id);

        self::assertNotNull($product);
        self::assertTrue(Money::PLN(self::PRICE_IN_GROSZE)->equals($product->price));
        self::assertSame(self::STOCK - self::RESERVED, $product->availableQuantity());
    }

    public function testPagesByIdAfterTheCursor(): void
    {
        $ids = TestProductData::create($this->pdo, $this->prefix, 3, self::PRICE_IN_GROSZE, self::STOCK);

        $page = $this->products->findPage(2, $ids[0]);

        self::assertSame([$ids[1], $ids[2]], array_map(static fn ($product): int => $product->id, $page));
    }

    public function testLocksRequestedProductsOrderedById(): void
    {
        $ids = TestProductData::create($this->pdo, $this->prefix, 3, self::PRICE_IN_GROSZE, self::STOCK);

        $this->pdo->beginTransaction();
        $locked = $this->products->lockForUpdate([$ids[2], $ids[0]]);
        $this->pdo->commit();

        self::assertSame([$ids[0], $ids[2]], array_map(static fn ($product): int => $product->id, $locked));
    }

    public function testReservesOnlyWhileEnoughStockIsAvailable(): void
    {
        [$id] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK, self::RESERVED);
        $available = self::STOCK - self::RESERVED;

        self::assertTrue($this->products->reserve($id, $available));
        self::assertFalse($this->products->reserve($id, 1));
        $product = $this->products->findById($id);
        self::assertNotNull($product);
        self::assertSame(0, $product->availableQuantity());
        self::assertSame(2, $product->version);
    }

    public function testReturnsNullForUnknownProduct(): void
    {
        self::assertNull($this->products->findById(PHP_INT_MAX));
    }

    public function testDatabaseRejectsReservationsExceedingStock(): void
    {
        [$id] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);

        $this->expectException(PDOException::class);
        $this->pdo->prepare(self::OVER_RESERVE_QUERY)->execute(['id' => $id]);
    }
}
