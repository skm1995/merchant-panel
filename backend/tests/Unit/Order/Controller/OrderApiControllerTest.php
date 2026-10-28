<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Session\CurrentUser;
use App\Cart\Repository\CartRepositoryInterface;
use App\Cart\Service\CartService;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Catalog\Service\StockReservation;
use App\Order\Command\CancelOrderHandler;
use App\Order\Command\PlaceOrderHandler;
use App\Order\Controller\OrderApiController;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Query\GetOrderHandler;
use App\Order\Query\ListOrdersHandler;
use App\Order\ReadModel\OrderSummary;
use App\Order\Repository\OrderReadRepositoryInterface;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderStatusChange;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Session;
use App\Shared\Messaging\Outbox\OutboxInterface;
use DateTimeImmutable;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Support\MerchantUserRepository;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class OrderApiControllerTest extends TestCase
{
    private const string CHECKOUT_PATH = '/api/v1/carts/' . CartFixtures::CART_ID . '/checkout';
    private const string ORDER_PATH = '/api/v1/orders/' . OrderFixtures::ORDER_ID;
    private const int RESERVATION_SECONDS = 900;
    private const string PANEL_ORDERS_PATH = '/api/v1/panel/orders';
    private const string ORDER_TOKEN_HEADER = 'x-order-token';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testCheckoutWithoutIdempotencyKeyIsBadRequest(): void
    {
        $carts = $this->createStub(CartRepositoryInterface::class);

        $body = $this->dispatch(new Request(HttpMethodEnum::POST->value, self::CHECKOUT_PATH), $carts);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $body['status']);
    }


    public function testHidesOrderFromAWrongAccessToken(): void
    {
        $body = $this->dispatch(
            new Request(HttpMethodEnum::GET->value, self::ORDER_PATH, headers: [self::ORDER_TOKEN_HEADER => OrderFixtures::WRONG_ACCESS_TOKEN]),
            $this->createStub(CartRepositoryInterface::class),
        );

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $body['status']);
    }

    public function testShowingAnOrderWithoutItsAccessTokenIsBadRequest(): void
    {
        $body = $this->dispatch(new Request(HttpMethodEnum::GET->value, self::ORDER_PATH), $this->createStub(CartRepositoryInterface::class));

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $body['status']);
    }

    public function testListsOrdersOnlyForSignedInMerchants(): void
    {
        $session = new Session();
        $session->start();
        $originalSession = $_SESSION;
        $_SESSION = [];

        try {
            $anonymous = $this->dispatch(new Request(HttpMethodEnum::GET->value, self::PANEL_ORDERS_PATH), $this->createStub(CartRepositoryInterface::class));
            self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $anonymous['status']);

            new CurrentUser($session, new MerchantUserRepository())->signIn(new User('merchant@example.com', 'hash', new DateTimeImmutable()));
            $page = $this->dispatch(new Request(HttpMethodEnum::GET->value, self::PANEL_ORDERS_PATH), $this->createStub(CartRepositoryInterface::class));
        } finally {
            $_SESSION = $originalSession;
        }

        self::assertIsArray($page['data']);
        self::assertCount(1, $page['data']);
        self::assertNull($page['next_cursor']);
    }

    /** @return array<string, mixed> */
    private function dispatch(Request $request, CartRepositoryInterface $carts): array
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $readOrders = $this->createStub(OrderReadRepositoryInterface::class);
        $readOrders->method('find')->willReturn(OrderFixtures::view());
        $readOrders->method('findPage')->willReturn([
            new OrderSummary(OrderFixtures::orderId(), OrderStatusEnum::PAID, Money::PLN(1000), new DateTimeImmutable()),
        ]);
        $transactions = new ImmediateTransactionRunner();
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $stock = new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock());

        return HttpHarness::json($request, new OrderApiController(
            new PlaceOrderHandler(
                $this->createStub(OrderRepositoryInterface::class),
                new CartService($carts, new ProductCatalog($products), $transactions),
                $stock,
                $this->createStub(OutboxInterface::class),
                $transactions,
                new FrozenClock(),
                OrderFixtures::access(),
                self::RESERVATION_SECONDS,
            ),
            new GetOrderHandler($readOrders, OrderFixtures::access()),
            new CancelOrderHandler($orders, new OrderStatusChange($orders, $stock), $transactions, OrderFixtures::access()),
            new ListOrdersHandler($readOrders),
            HttpHarness::responder(),
        ));
    }
}
