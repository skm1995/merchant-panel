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
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestCheckout;
use Tests\Functional\Support\TestProductData;

final class OrderCancellationApiTest extends TestCase
{
    private const string SKU_PREFIX = 'cancel-';
    private const string ORDER_PATH_FORMAT = '/api/v1/orders/%s';
    private const string CANCEL_PATH_FORMAT = '/api/v1/orders/%s/cancel';
    private const string CANCEL_SPECIFICATION_PATH = '/api/v1/orders/{id}/cancel';
    private const string FIRST_VERSION_TAG = '"1"';
    private const string SECOND_VERSION_TAG = '"2"';
    private const string WRONG_TOKEN = '0000000000000000000000000000000000000000000000000000000000000000';
    private const int PRICE_IN_GROSZE = 1000;
    private const int STOCK = 5;
    private const int QUANTITY = 2;

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

    public function testCancellationRequiresTheCurrentEntityTagAndReleasesStock(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
        $order = $this->placeOrder($productId);
        $cancelPath = sprintf(self::CANCEL_PATH_FORMAT, $order->orderId);

        $shown = ApiClient::sendWithQuery(sprintf(self::ORDER_PATH_FORMAT, $order->orderId), [], $order->headers());
        $withoutPrecondition = ApiClient::send(HttpMethodEnum::POST, $cancelPath, headers: $order->headers());
        $stale = $this->cancel($order, self::SECOND_VERSION_TAG);
        $cancelled = $this->cancel($order, self::FIRST_VERSION_TAG);
        $again = $this->cancel($order, self::SECOND_VERSION_TAG);

        self::assertSame(self::FIRST_VERSION_TAG, $shown->getHeaderLine(HeaderNameEnum::ETAG->value));
        self::assertSame(ResponseStatusEnum::PRECONDITION_REQUIRED->value, $withoutPrecondition->getStatusCode());
        self::assertSame(ResponseStatusEnum::PRECONDITION_FAILED->value, $stale->getStatusCode());
        self::assertSame(ResponseStatusEnum::OK->value, $cancelled->getStatusCode());
        self::assertSame('cancelled', ApiClient::json($cancelled)['status']);
        self::assertSame(self::SECOND_VERSION_TAG, $cancelled->getHeaderLine(HeaderNameEnum::ETAG->value));
        self::assertSame(ResponseStatusEnum::CONFLICT->value, $again->getStatusCode());
        self::assertSame(self::STOCK, $this->products->findById($productId)?->availableQuantity());
        foreach ([$withoutPrecondition, $stale, $cancelled, $again] as $response) {
            ApiClient::assertMatchesSpecification(self::CANCEL_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $response);
        }
    }

    public function testConcurrentCancellationsWithTheSameTagChangeTheOrderOnce(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
        $order = $this->placeOrder($productId);
        $request = [HttpMethodEnum::POST, sprintf(self::CANCEL_PATH_FORMAT, $order->orderId), $order->headers([HeaderNameEnum::IF_MATCH->value => self::FIRST_VERSION_TAG])];

        $statuses = array_count_values(array_map(
            static fn ($response): int => $response->getStatusCode(),
            ApiClient::sendConcurrently(array_fill(0, 5, $request)),
        ));

        self::assertSame(1, $statuses[ResponseStatusEnum::OK->value] ?? 0);
        self::assertSame(4, $statuses[ResponseStatusEnum::PRECONDITION_FAILED->value] ?? 0);
        self::assertSame(0, $this->products->findById($productId)?->reserved);
    }

    public function testTheOrderIdAloneCannotCancelTheOrder(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
        $order = $this->placeOrder($productId);
        $cancelPath = sprintf(self::CANCEL_PATH_FORMAT, $order->orderId);

        $withoutToken = ApiClient::send(HttpMethodEnum::POST, $cancelPath, headers: [HeaderNameEnum::IF_MATCH->value => self::FIRST_VERSION_TAG]);
        $wrongToken = ApiClient::send(HttpMethodEnum::POST, $cancelPath, headers: [
            HeaderNameEnum::IF_MATCH->value => self::FIRST_VERSION_TAG,
            HeaderNameEnum::ORDER_TOKEN->value => self::WRONG_TOKEN,
        ]);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $withoutToken->getStatusCode());
        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $wrongToken->getStatusCode());
        self::assertSame(self::STOCK - self::QUANTITY, $this->products->findById($productId)?->availableQuantity());
        foreach ([$withoutToken, $wrongToken] as $response) {
            ApiClient::assertMatchesSpecification(self::CANCEL_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $response);
        }
    }

    private function placeOrder(int $productId): TestCheckout
    {
        $order = TestCheckout::place($productId, self::QUANTITY);
        $this->cartIds[] = $order->cartId;

        return $order;
    }

    private function cancel(TestCheckout $order, string $entityTag): ResponseInterface
    {
        return ApiClient::send(
            HttpMethodEnum::POST,
            sprintf(self::CANCEL_PATH_FORMAT, $order->orderId),
            headers: $order->headers([HeaderNameEnum::IF_MATCH->value => $entityTag]),
        );
    }
}
