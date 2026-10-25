<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Controller;

use App\Cart\Repository\CartRepositoryInterface;
use App\Cart\Service\CartService;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Catalog\Service\StockReservation;
use App\Order\Command\CancelOrderHandler;
use App\Order\Command\PlaceOrderHandler;
use App\Order\Controller\OrderApiController;
use App\Order\Query\GetOrderHandler;
use App\Order\Repository\OrderReadRepositoryInterface;
use App\Order\Repository\OrderRepositoryInterface;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class OrderApiControllerTest extends TestCase
{
    private const string CHECKOUT_PATH = '/api/v1/carts/' . CartFixtures::CART_ID . '/checkout';
    private const string ORDER_PATH = '/api/v1/orders/' . OrderFixtures::ORDER_ID;

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testCheckoutWithoutIdempotencyKeyIsBadRequest(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->expects(self::never())->method('lockStatus');

        $body = $this->dispatch(new Request(HttpMethodEnum::POST->value, self::CHECKOUT_PATH), $carts);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $body['status']);
    }

    public function testShowsOrder(): void
    {
        $body = $this->dispatch(
            new Request(HttpMethodEnum::GET->value, self::ORDER_PATH),
            $this->createStub(CartRepositoryInterface::class),
        );

        self::assertSame(OrderFixtures::ORDER_ID, $body['id']);
    }

    /** @return array<string, mixed> */
    private function dispatch(Request $request, CartRepositoryInterface $carts): array
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $readOrders = $this->createStub(OrderReadRepositoryInterface::class);
        $readOrders->method('find')->willReturn(OrderFixtures::view());
        $transactions = new ImmediateTransactionRunner();
        $responder = new Responder(new CsrfToken(new Session()));
        $router = new Router(new CsrfToken(new Session()), $responder);
        $router->registerController(new OrderApiController(
            new PlaceOrderHandler(
                $this->createStub(OrderRepositoryInterface::class),
                new CartService($carts, new ProductCatalog($products), $transactions),
                new StockReservation($products),
                $this->createStub(OutboxInterface::class),
                $transactions,
                new MockClock(),
                900,
            ),
            new GetOrderHandler($readOrders),
            new CancelOrderHandler(
                $this->createStub(OrderRepositoryInterface::class),
                new StockReservation($products),
                $transactions,
            ),
            $responder,
        ));
        ob_start();
        $router->dispatch($request);
        $output = ob_get_clean();
        self::assertIsString($output);
        $body = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
