<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Infrastructure\Cache;

use App\Catalog\Entity\Product;
use App\Catalog\Infrastructure\Cache\ProductCache;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Shared\Cache\StampedeProtectedCache;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\InMemoryCacheStore;
use Tests\Unit\Support\FrozenClock;

final class ProductCacheTest extends TestCase
{
    private const int PRODUCT_ID = 7;
    private const int MISSING_ID = 404;

    private InMemoryCacheStore $store;
    private ProductCache $cache;
    private int $loads = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryCacheStore();
        $this->cache = new ProductCache(
            new StampedeProtectedCache($this->store, new FrozenClock(), static fn (): float => 1.0, 0),
            $this->store,
        );
    }

    public function testLoadsAProductOnceAndRestoresItFromCache(): void
    {
        $first = $this->cache->remember(self::PRODUCT_ID, $this->load(...));
        $second = $this->cache->remember(self::PRODUCT_ID, $this->load(...));

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(1, $this->loads);
        self::assertEquals($first, $second);
        self::assertSame(7, $second->availableQuantity());
        self::assertTrue($first->price->equals($second->price));
    }

    public function testCachesMissingProductsToShieldTheDatabase(): void
    {
        self::assertNull($this->cache->remember(self::MISSING_ID, $this->load(...)));
        self::assertNull($this->cache->remember(self::MISSING_ID, $this->load(...)));
        self::assertSame(1, $this->loads);
    }

    public function testForgetsProductsWhenTheirStockChanges(): void
    {
        $this->cache->remember(self::PRODUCT_ID, $this->load(...));

        $this->cache->handle(new ProductStockChangedMessage([self::PRODUCT_ID]));
        $this->cache->remember(self::PRODUCT_ID, $this->load(...));

        self::assertSame(2, $this->loads);
    }

    private function load(int $id): ?Product
    {
        $this->loads++;

        return $id === self::PRODUCT_ID ? ProductFactory::create(self::PRODUCT_ID, 10, 3) : null;
    }
}
