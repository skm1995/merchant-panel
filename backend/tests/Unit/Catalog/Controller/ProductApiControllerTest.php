<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Controller;

use App\Catalog\Controller\ProductApiController;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Catalog\Service\ProductEditService;
use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Catalog\ProductFactory;

final class ProductApiControllerTest extends TestCase
{
    private const string PRODUCTS_PATH = '/api/v1/products';
    private const string PRODUCT_PATH = '/api/v1/products/7';
    private const string NON_NUMERIC_PRODUCT_PATH = '/api/v1/products/abc';
    private const string LIMIT_PARAMETER = 'limit';
    private const string PANEL_PRODUCT_PATH = '/api/v1/panel/products/7';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }


    public function testPageSizeAboveMaximumIsBadRequest(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);

        $this->get(self::PRODUCTS_PATH, $products, [
            self::LIMIT_PARAMETER => (string) (ProductCatalog::MAXIMUM_PAGE_SIZE + 1),
        ]);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
    }


    public function testMissingProductIsNotFoundProblem(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findById')->willReturn(null);

        $body = $this->get(self::PRODUCT_PATH, $products);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, http_response_code());
        self::assertSame(ResponseStatusEnum::NOT_FOUND->message(), $body['title']);
    }

    public function testNonNumericProductIdDoesNotReachTheCatalog(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);

        $this->get(self::NON_NUMERIC_PRODUCT_PATH, $products, decodeJson: false);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, http_response_code());
    }

    public function testEditingAProductRequiresASignedInMerchant(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('update');
        $request = new Request(HttpMethodEnum::PATCH->value, self::PANEL_PRODUCT_PATH, headers: ['if-match' => '"1"'], body: '{"price":{"amount":"2500","currency":"PLN"},"stock":12}');

        HttpHarness::dispatch($request, $this->controller($products));

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function get(
        string $path,
        ProductRepositoryInterface $products,
        array $query = [],
        bool $decodeJson = true,
    ): array {
        $request = new Request(HttpMethodEnum::GET->value, $path, $query);
        $controller = $this->controller($products);

        if (!$decodeJson) {
            HttpHarness::dispatch($request, $controller);

            return [];
        }

        return HttpHarness::json($request, $controller);
    }

    private function controller(ProductRepositoryInterface $products): ProductApiController
    {
        return new ProductApiController(
            new ProductCatalog($products),
            new ProductEditService($products, $this->createStub(OutboxInterface::class), new ImmediateTransactionRunner(), new FrozenClock()),
            HttpHarness::responder(),
        );
    }
}
