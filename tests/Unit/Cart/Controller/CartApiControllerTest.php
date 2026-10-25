<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Controller;

use App\Cart\Controller\CartApiController;
use App\Cart\Enum\CartStatusEnum;
use App\Cart\Repository\CartRepositoryInterface;
use App\Cart\Service\CartService;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class CartApiControllerTest extends TestCase
{
    private const string CARTS_PATH = '/api/v1/carts';
    private const string CART_PATH = '/api/v1/carts/' . CartFixtures::CART_ID;
    private const string ITEM_PATH = '/api/v1/carts/' . CartFixtures::CART_ID . '/items/5';
    private const string QUANTITY_BODY = '{"quantity":2}';
    private const string TOO_LARGE_QUANTITY_BODY = '{"quantity":1000}';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testCreatesCart(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->expects(self::once())->method('create');

        $body = $this->dispatch(new Request(HttpMethodEnum::POST->value, self::CARTS_PATH), $carts);

        self::assertSame(ResponseStatusEnum::CREATED->value, http_response_code());
        self::assertSame(CartStatusEnum::OPEN->value, $body['status']);
    }

    public function testShowsCart(): void
    {
        $carts = $this->createStub(CartRepositoryInterface::class);
        $carts->method('find')->willReturn(CartFixtures::cart());

        $body = $this->dispatch(new Request(HttpMethodEnum::GET->value, self::CART_PATH), $carts);

        self::assertSame(CartFixtures::CART_ID, $body['id']);
    }

    public function testCheckedOutCartRejectsChangesWithConflict(): void
    {
        $carts = $this->createStub(CartRepositoryInterface::class);
        $carts->method('lockStatus')->willReturn(CartStatusEnum::CHECKED_OUT);

        $this->dispatch(new Request(HttpMethodEnum::PUT->value, self::ITEM_PATH, body: self::QUANTITY_BODY), $carts);

        self::assertSame(ResponseStatusEnum::CONFLICT->value, http_response_code());
    }

    public function testQuantityOutsideRangeIsUnprocessable(): void
    {
        $this->dispatch(
            new Request(HttpMethodEnum::PUT->value, self::ITEM_PATH, body: self::TOO_LARGE_QUANTITY_BODY),
            $this->createStub(CartRepositoryInterface::class),
        );

        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, http_response_code());
    }

    /** @return array<string, mixed> */
    private function dispatch(Request $request, CartRepositoryInterface $carts): array
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findById')->willReturn(ProductFactory::create(5));
        $responder = new Responder(new CsrfToken(new Session()));
        $router = new Router(new CsrfToken(new Session()), $responder);
        $router->registerController(new CartApiController(
            new CartService($carts, new ProductCatalog($products), new ImmediateTransactionRunner()),
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
