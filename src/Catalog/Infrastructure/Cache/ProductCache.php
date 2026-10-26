<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Cache;

use App\Catalog\Entity\Product;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Shared\Cache\CacheStoreInterface;
use App\Shared\Cache\StampedeProtectedCache;
use App\Shared\Messaging\AsMessageHandler;
use App\Shared\Serialization\JsonDecoder;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;
use UnexpectedValueException;

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
    private const string CORRUPT_ENTRY_MESSAGE = 'The cached product entry is corrupt.';
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

    /**
     * A corrupt entry, for example one written by an older version, is a cache miss: it is
     * removed and the product is loaded again.
     *
     * @param callable(int): ?Product $load
     */
    public function remember(int $id, callable $load): ?Product
    {
        $compute = static fn (): string => self::encode($load($id));

        try {
            return self::decode($this->cache->remember(self::key($id), self::TTL_SECONDS, $compute));
        } catch (UnexpectedValueException) {
            $this->forget([$id]);

            return self::decode($this->cache->remember(self::key($id), self::TTL_SECONDS, $compute));
        }
    }

    /** @param list<int> $ids */
    public function forget(array $ids): void
    {
        $this->store->delete(array_map(self::key(...), $ids));
    }

    #[AsMessageHandler]
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

    /** @throws UnexpectedValueException when the entry is not a valid encoded product */
    private static function decode(string $encoded): ?Product
    {
        if ($encoded === self::MISSING) {
            return null;
        }

        $data = JsonDecoder::toArray($encoded);
        $currency = $data[self::PRICE_CURRENCY_KEY] ?? null;
        $integers = [self::ID_KEY, self::PRICE_AMOUNT_KEY, self::STOCK_KEY, self::RESERVED_KEY, self::VERSION_KEY];

        if (
            $data === null
            || !is_string($data[self::SKU_KEY] ?? null)
            || !is_string($data[self::NAME_KEY] ?? null)
            || !is_string($currency)
            || strlen($currency) === 0
            || array_filter($integers, static fn (string $key): bool => !is_numeric($data[$key] ?? null)) !== []
        ) {
            throw new UnexpectedValueException(self::CORRUPT_ENTRY_MESSAGE);
        }

        try {
            return new Product(
                (int) $data[self::ID_KEY],
                $data[self::SKU_KEY],
                $data[self::NAME_KEY],
                new Money((int) $data[self::PRICE_AMOUNT_KEY], new Currency($currency)),
                (int) $data[self::STOCK_KEY],
                (int) $data[self::RESERVED_KEY],
                (int) $data[self::VERSION_KEY],
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedValueException(self::CORRUPT_ENTRY_MESSAGE, previous: $exception);
        }
    }
}
