<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Infrastructure\Cache;

use App\Catalog\Entity\Product;
use App\Catalog\Infrastructure\Cache\ProductCache;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Shared\Cache\CacheEntry;
use App\Shared\Cache\StampedeProtectedCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\InMemoryCacheStore;
use Tests\Unit\Support\FrozenClock;

final class ProductCacheTest extends TestCase
{
    private const int PRODUCT_ID = 7;
    private const int MISSING_ID = 404;
    private const string PRODUCT_KEY = 'catalog:product:7';
    private const int ENTRY_TTL = 60;
    private const float FAR_FUTURE = 9999999999.0;

    private InMemoryCacheStore $store;
    private ProductCache $cache;
    private int $loads = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryCacheStore();
        $this->cache = new ProductCache(
            new StampedeProtectedCache($this->store, new FrozenClock(), new NullLogger(), static fn (): float => 1.0, 0),
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

    /** @return iterable<string, array{string}> */
    public static function corruptEntries(): iterable
    {
        yield 'not JSON' => ['{broken'];
        yield 'missing fields' => ['{"id": 7}'];
        yield 'impossible stock' => ['{"id":7,"sku":"s","name":"n","price_amount":"100","price_currency":"PLN","stock":1,"reserved":5,"version":1}'];
    }

    #[DataProvider('corruptEntries')]
    public function testTreatsACorruptEntryAsACacheMissAndRepairsIt(string $corrupt): void
    {
        $this->store->set(self::PRODUCT_KEY, new CacheEntry($corrupt, 0.0, self::FAR_FUTURE)->encode(), self::ENTRY_TTL);

        $product = $this->cache->remember(self::PRODUCT_ID, $this->load(...));
        $this->cache->remember(self::PRODUCT_ID, $this->load(...));

        self::assertNotNull($product);
        self::assertSame(self::PRODUCT_ID, $product->id);
        self::assertSame(1, $this->loads);
    }

    private function load(int $id): ?Product
    {
        $this->loads++;

        return $id === self::PRODUCT_ID ? ProductFactory::create(self::PRODUCT_ID, 10, 3) : null;
    }
}
