<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Cache;

use App\Catalog\Entity\Product;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Shared\Cache\CacheStoreInterface;
use App\Shared\Cache\StampedeProtectedCache;
use JsonException;
use Money\Currency;
use Money\Money;

/**
 * Owns the cache keys, TTL, and serialization of products. Missing products are cached too,
 * so repeated lookups of unknown ids do not reach the database; the short TTL bounds how
 * long a value can be stale if an invalidation is lost.
 */
final class ProductCache
{
    public const int TTL_SECONDS = 60;
    private const string KEY_FORMAT = 'catalog:product:%d';
    private const string MISSING = 'null';
    private const string ID_KEY = 'id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string PRICE_AMOUNT_KEY = 'price_amount';
    private const string PRICE_CURRENCY_KEY = 'price_currency';
    private const string STOCK_KEY = 'stock';
    private const string RESERVED_KEY = 'reserved';
    private const string VERSION_KEY = 'version';

    public function __construct(
        private readonly StampedeProtectedCache $cache,
        private readonly CacheStoreInterface $store,
    ) {
    }

    /** @param callable(int): ?Product $load */
    public function remember(int $id, callable $load): ?Product
    {
        $encoded = $this->cache->remember(
            self::key($id),
            self::TTL_SECONDS,
            static fn (): string => self::encode($load($id)),
        );

        return self::decode($encoded);
    }

    /** @param list<int> $ids */
    public function forget(array $ids): void
    {
        $this->store->delete(array_map(self::key(...), $ids));
    }

    public function handle(ProductStockChangedMessage $message): void
    {
        $this->forget($message->productIds);
    }

    private static function key(int $id): string
    {
        return sprintf(self::KEY_FORMAT, $id);
    }

    private static function encode(?Product $product): string
    {
        if ($product === null) {
            return self::MISSING;
        }

        return json_encode([
            self::ID_KEY => $product->id,
            self::SKU_KEY => $product->sku,
            self::NAME_KEY => $product->name,
            self::PRICE_AMOUNT_KEY => $product->price->getAmount(),
            self::PRICE_CURRENCY_KEY => $product->price->getCurrency()->getCode(),
            self::STOCK_KEY => $product->stock,
            self::RESERVED_KEY => $product->reserved,
            self::VERSION_KEY => $product->version,
        ], JSON_THROW_ON_ERROR);
    }

    private static function decode(string $encoded): ?Product
    {
        try {
            $data = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        /** @var non-empty-string $currency */
        $currency = (string) $data[self::PRICE_CURRENCY_KEY];

        return new Product(
            (int) $data[self::ID_KEY],
            (string) $data[self::SKU_KEY],
            (string) $data[self::NAME_KEY],
            new Money((int) $data[self::PRICE_AMOUNT_KEY], new Currency($currency)),
            (int) $data[self::STOCK_KEY],
            (int) $data[self::RESERVED_KEY],
            (int) $data[self::VERSION_KEY],
        );
    }
}
