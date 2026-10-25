<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Infrastructure\Cache;

use App\Catalog\Infrastructure\Cache\CachedProductRepository;
use App\Catalog\Infrastructure\Cache\ProductCache;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Shared\Cache\StampedeProtectedCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\InMemoryCacheStore;

final class CachedProductRepositoryTest extends TestCase
{
    private const int PRODUCT_ID = 7;

    public function testReadsSingleProductsThroughTheCache(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method('findById')->with(self::PRODUCT_ID)->willReturn(ProductFactory::create(self::PRODUCT_ID));
        $repository = $this->repository($products);

        $repository->findById(self::PRODUCT_ID);

        self::assertSame(self::PRODUCT_ID, $repository->findById(self::PRODUCT_ID)?->id);
    }

    public function testSendsLocksAndStockChangesStraightToTheDatabase(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method('lockForUpdate')->with([self::PRODUCT_ID])->willReturn([]);
        $products->expects(self::once())->method('reserve')->with(self::PRODUCT_ID, 1)->willReturn(true);
        $products->expects(self::once())->method('release')->with(self::PRODUCT_ID, 1)->willReturn(true);
        $products->expects(self::once())->method('commit')->with(self::PRODUCT_ID, 1)->willReturn(true);
        $products->expects(self::once())->method('findPage')->with(10, null)->willReturn([]);
        $repository = $this->repository($products);

        $repository->lockForUpdate([self::PRODUCT_ID]);
        $repository->reserve(self::PRODUCT_ID, 1);
        $repository->release(self::PRODUCT_ID, 1);
        $repository->commit(self::PRODUCT_ID, 1);
        $repository->findPage(10, null);
    }

    private function repository(ProductRepositoryInterface $products): CachedProductRepository
    {
        $store = new InMemoryCacheStore();

        return new CachedProductRepository(
            $products,
            new ProductCache(new StampedeProtectedCache($store, new MockClock(), static fn (): float => 1.0, 0), $store),
        );
    }
}
