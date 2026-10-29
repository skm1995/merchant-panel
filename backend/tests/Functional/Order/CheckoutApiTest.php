<?php

declare(strict_types=1);

namespace Tests\Functional\Order;

use App\Cart\Entity\CartId;
use App\Catalog\Repository\PdoProductRepository;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestProductData;

final class CheckoutApiTest extends TestCase
{
    private const string SKU_PREFIX = 'checkout-';
    private const string KEY_PREFIX = 'checkout-key-';
    private const string CARTS_PATH = '/api/v1/carts';
    private const string ITEM_PATH_FORMAT = '/api/v1/carts/%s/items/%d';
    private const string CHECKOUT_PATH_FORMAT = '/api/v1/carts/%s/checkout';
    private const string ORDER_PATH_FORMAT = '/api/v1/orders/%s';
    private const string WRONG_TOKEN = '0000000000000000000000000000000000000000000000000000000000000000';
    private const string CHECKOUT_SPECIFICATION_PATH = '/api/v1/carts/{id}/checkout';
    private const string ORDER_SPECIFICATION_PATH = '/api/v1/orders/{id}';
    private const string ITEM_SPECIFICATION_PATH = '/api/v1/carts/{id}/items/{productId}';
    private const string IDEMPOTENCY_KEY_HEADER = 'Idempotency-Key';
    private const int PRICE_IN_GROSZE = 2500;
    private const int CONCURRENT_BUYERS = 10;

    private PDO $pdo;
    private PdoProductRepository $products;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->products = new PdoProductRepository($this->pdo);
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testCheckoutReservesStockAndFreezesPrices(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 5);
        $cartId = $this->cartWith([$productId => 2]);

        $response = $this->checkout($cartId, self::uniqueKey());
        $order = ApiClient::json($response);
        $token = $response->getHeaderLine(HeaderNameEnum::ORDER_TOKEN->value);
        $shown = ApiClient::sendWithQuery(sprintf(self::ORDER_PATH_FORMAT, $order['id']), [], [HeaderNameEnum::ORDER_TOKEN->value => $token]);
        $withoutToken = ApiClient::get(sprintf(self::ORDER_PATH_FORMAT, $order['id']));
        $wrongToken = ApiClient::sendWithQuery(sprintf(self::ORDER_PATH_FORMAT, $order['id']), [], [HeaderNameEnum::ORDER_TOKEN->value => self::WRONG_TOKEN]);

        self::assertSame(ResponseStatusEnum::CREATED->value, $response->getStatusCode());
        self::assertSame(sprintf(self::ORDER_PATH_FORMAT, $order['id']), $response->getHeaderLine(HeaderNameEnum::LOCATION->value));
        ApiClient::assertMatchesSpecification(self::CHECKOUT_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $response);
        ApiClient::assertMatchesSpecification(self::ORDER_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $shown);
        self::assertSame(ResponseStatusEnum::OK->value, $shown->getStatusCode());
        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $withoutToken->getStatusCode(), 'The order id alone opens nothing.');
        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $wrongToken->getStatusCode());
        ApiClient::assertMatchesSpecification(self::ORDER_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $withoutToken);
        ApiClient::assertMatchesSpecification(self::ORDER_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $wrongToken);
        self::assertSame(['amount' => (string) (2 * self::PRICE_IN_GROSZE), 'currency' => 'PLN'], $order['total']);
        self::assertSame(3, $this->products->findById($productId)?->availableQuantity());
    }

    public function testRepeatedRequestWithSameKeyReturnsTheSameOrderOnce(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 5);
        $cartId = $this->cartWith([$productId => 1]);
        $key = self::uniqueKey();

        $firstResponse = $this->checkout($cartId, $key);
        $first = ApiClient::json($firstResponse);
        $repeated = $this->checkout($cartId, $key);

        self::assertSame(ResponseStatusEnum::CREATED->value, $repeated->getStatusCode());
        self::assertSame($first['id'], ApiClient::json($repeated)['id']);
        self::assertSame($firstResponse->getHeaderLine(HeaderNameEnum::ORDER_TOKEN->value), $repeated->getHeaderLine(HeaderNameEnum::ORDER_TOKEN->value));
        self::assertSame(4, $this->products->findById($productId)?->availableQuantity());
    }

    public function testRejectsKeyReuseForAnotherCartAndRepeatedCheckoutWithNewKey(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 5);
        $firstCart = $this->cartWith([$productId => 1]);
        $secondCart = $this->cartWith([$productId => 1]);
        $key = self::uniqueKey();
        $this->checkout($firstCart, $key);

        $reused = $this->checkout($secondCart, $key);
        $checkedOutAgain = $this->checkout($firstCart, self::uniqueKey());

        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, $reused->getStatusCode());
        self::assertSame(ResponseStatusEnum::CONFLICT->value, $checkedOutAgain->getStatusCode());
        ApiClient::assertMatchesSpecification(self::CHECKOUT_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $reused);
    }

    /**
     * The shop removes its own generated and simulated orders by these key prefixes, so a
     * customer's order must never carry one.
     */
    #[DataProvider('reservedKeyPrefixes')]
    public function testRefusesKeysReservedForTheShopsOwnOrders(string $prefix): void
    {
        [$product] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 5);
        $cartId = $this->cartWith([$product => 1]);

        $response = $this->checkout($cartId, $prefix . uniqid());

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $response->getStatusCode(), (string) $response->getBody());
        ApiClient::assertMatchesSpecification(self::CHECKOUT_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $response);
        self::assertSame(0, $this->products->findById($product)?->reserved, 'No stock is reserved for a refused checkout.');
    }

    /** @return iterable<string, array{string}> */
    public static function reservedKeyPrefixes(): iterable
    {
        yield 'generated sales history' => ['history:'];
        yield 'Black Friday simulation, without the simulator key' => ['simulation:'];
    }

    public function testRequiresAnIdempotencyKey(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 5);
        $cartId = $this->cartWith([$productId => 1]);

        $response = ApiClient::send(HttpMethodEnum::POST, sprintf(self::CHECKOUT_PATH_FORMAT, $cartId));

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $response->getStatusCode());
        ApiClient::assertMatchesSpecification(self::CHECKOUT_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $response);
        self::assertSame(0, $this->products->findById($productId)?->reserved);
    }

    public function testCheckedOutCartRefusesChanges(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 5);
        $cartId = $this->cartWith([$productId => 1]);
        $this->checkout($cartId, self::uniqueKey());

        $response = ApiClient::send(HttpMethodEnum::PUT, sprintf(self::ITEM_PATH_FORMAT, $cartId, $productId), ['quantity' => 2]);

        self::assertSame(ResponseStatusEnum::CONFLICT->value, $response->getStatusCode());
        ApiClient::assertMatchesSpecification(self::ITEM_SPECIFICATION_PATH, HttpMethodEnum::PUT->value, $response);
        self::assertSame(1, $this->products->findById($productId)?->reserved, 'The order keeps the quantity it reserved.');
    }

    public function testRejectsEmptyCartAndMissingStock(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 1);
        $emptyCart = $this->cartWith([]);
        $tooLargeCart = $this->cartWith([$productId => 2]);

        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, $this->checkout($emptyCart, self::uniqueKey())->getStatusCode());
        self::assertSame(ResponseStatusEnum::CONFLICT->value, $this->checkout($tooLargeCart, self::uniqueKey())->getStatusCode());
        self::assertSame(1, $this->products->findById($productId)?->availableQuantity());
    }

    public function testOnlyOneOfManyConcurrentBuyersGetsTheLastItem(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, 1);
        $carts = array_map(fn (): string => $this->cartWith([$productId => 1]), range(1, self::CONCURRENT_BUYERS));

        $statuses = $this->checkoutConcurrently($carts);

        self::assertSame(1, $statuses[ResponseStatusEnum::CREATED->value] ?? 0);
        self::assertSame(self::CONCURRENT_BUYERS - 1, $statuses[ResponseStatusEnum::CONFLICT->value] ?? 0);
        $product = $this->products->findById($productId);
        self::assertNotNull($product);
        self::assertSame(1, $product->reserved);
    }

    public function testConcurrentCheckoutsOfOverlappingProductsDoNotDeadlock(): void
    {
        [$first, $second] = TestProductData::create($this->pdo, $this->prefix, 2, self::PRICE_IN_GROSZE, 100);
        $carts = array_map(
            fn (int $buyer): string => $this->cartWith($buyer % 2 === 0 ? [$first => 1, $second => 1] : [$second => 1, $first => 1]),
            range(1, self::CONCURRENT_BUYERS),
        );

        $statuses = $this->checkoutConcurrently($carts);

        self::assertSame([ResponseStatusEnum::CREATED->value => self::CONCURRENT_BUYERS], $statuses);
        self::assertSame(self::CONCURRENT_BUYERS, $this->products->findById($first)?->reserved);
        self::assertSame(self::CONCURRENT_BUYERS, $this->products->findById($second)?->reserved);
    }

    /** @param array<int, int> $quantities */
    private function cartWith(array $quantities): string
    {
        $cartId = ApiClient::json(ApiClient::send(HttpMethodEnum::POST, self::CARTS_PATH))['id'];
        self::assertIsString($cartId);
        $this->cartIds[] = CartId::fromString($cartId);

        foreach ($quantities as $productId => $quantity) {
            ApiClient::send(HttpMethodEnum::PUT, sprintf(self::ITEM_PATH_FORMAT, $cartId, $productId), ['quantity' => $quantity]);
        }

        return $cartId;
    }

    private function checkout(string $cartId, string $key): ResponseInterface
    {
        return ApiClient::send(
            HttpMethodEnum::POST,
            sprintf(self::CHECKOUT_PATH_FORMAT, $cartId),
            headers: [self::IDEMPOTENCY_KEY_HEADER => $key],
        );
    }

    /**
     * @param list<string> $cartIds
     * @return array<int, int> number of responses per status code
     */
    private function checkoutConcurrently(array $cartIds): array
    {
        $responses = ApiClient::sendConcurrently(array_map(
            static fn (string $cartId): array => [
                HttpMethodEnum::POST,
                sprintf(self::CHECKOUT_PATH_FORMAT, $cartId),
                [self::IDEMPOTENCY_KEY_HEADER => self::uniqueKey()],
            ],
            $cartIds,
        ));

        return array_count_values(array_map(static fn (ResponseInterface $response): int => $response->getStatusCode(), $responses));
    }

    private static function uniqueKey(): string
    {
        return uniqid(self::KEY_PREFIX, true);
    }
}
