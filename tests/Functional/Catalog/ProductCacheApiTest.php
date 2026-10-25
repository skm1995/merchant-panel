<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog;

use App\Cart\Entity\CartId;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestProductData;

/**
 * Proves that product reads come from Redis and that a committed stock change invalidates
 * them through the outbox relay and the worker.
 */
final class ProductCacheApiTest extends TestCase
{
    private const string SKU_PREFIX = 'cache-';
    private const string KEY_PREFIX = 'cache-key-';
    private const string PRODUCT_PATH_FORMAT = '/api/v1/products/%d';
    private const string CARTS_PATH = '/api/v1/carts';
    private const string ITEM_PATH_FORMAT = '/api/v1/carts/%s/items/%d';
    private const string CHECKOUT_PATH_FORMAT = '/api/v1/carts/%s/checkout';
    private const string IDEMPOTENCY_KEY_HEADER = 'Idempotency-Key';
    private const string CHANGE_PRICE_BEHIND_THE_CACHE = 'UPDATE products SET price_amount = :price WHERE id = :id';
    private const int ORIGINAL_PRICE = 1000;
    private const int CHANGED_PRICE = 1500;
    private const int STOCK = 5;
    private const int WAIT_TIMEOUT_SECONDS = 15;
    private const int POLL_INTERVAL_MICROSECONDS = 200000;

    private PDO $pdo;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testServesCachedProductUntilACommittedStockChangeInvalidatesIt(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::ORIGINAL_PRICE, self::STOCK);
        $productPath = sprintf(self::PRODUCT_PATH_FORMAT, $productId);
        self::assertSame((string) self::ORIGINAL_PRICE, $this->price($productPath));

        $this->pdo->prepare(self::CHANGE_PRICE_BEHIND_THE_CACHE)->execute(['price' => self::CHANGED_PRICE, 'id' => $productId]);

        self::assertSame((string) self::ORIGINAL_PRICE, $this->price($productPath), 'The second read is served from Redis.');

        $this->checkout($productId);

        self::assertSame((string) self::CHANGED_PRICE, $this->waitForPrice($productPath, (string) self::CHANGED_PRICE));
        self::assertSame(self::STOCK - 1, ApiClient::json(ApiClient::get($productPath))['available_quantity']);
    }

    private function price(string $path): mixed
    {
        return ApiClient::json(ApiClient::get($path))['price']['amount'] ?? null;
    }

    private function waitForPrice(string $path, string $expected): mixed
    {
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $price = $this->price($path);

            if ($price === $expected) {
                return $price;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        return $price;
    }

    private function checkout(int $productId): void
    {
        $cartId = ApiClient::json(ApiClient::send(HttpMethodEnum::POST, self::CARTS_PATH))['id'];
        self::assertIsString($cartId);
        $this->cartIds[] = CartId::fromString($cartId);
        ApiClient::send(HttpMethodEnum::PUT, sprintf(self::ITEM_PATH_FORMAT, $cartId, $productId), ['quantity' => 1]);
        ApiClient::send(
            HttpMethodEnum::POST,
            sprintf(self::CHECKOUT_PATH_FORMAT, $cartId),
            headers: [self::IDEMPOTENCY_KEY_HEADER => uniqid(self::KEY_PREFIX, true)],
        );
    }
}
