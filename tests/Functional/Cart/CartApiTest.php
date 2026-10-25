<?php

declare(strict_types=1);

namespace Tests\Functional\Cart;

use App\Cart\Entity\CartId;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestProductData;

final class CartApiTest extends TestCase
{
    private const string SKU_PREFIX = 'cart-api-';
    private const string CARTS_PATH = '/api/v1/carts';
    private const string CART_PATH_FORMAT = '/api/v1/carts/%s';
    private const string ITEM_PATH_FORMAT = '/api/v1/carts/%s/items/%d';
    private const string CART_SPECIFICATION_PATH = '/api/v1/carts/{id}';
    private const string ITEM_SPECIFICATION_PATH = '/api/v1/carts/{id}/items/{productId}';
    private const string MISSING_CART_ID = '3f2b1c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
    private const int PRICE_IN_GROSZE = 1999;
    private const int STOCK = 10;

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

    public function testCreatesCartWithLocationHeader(): void
    {
        $response = ApiClient::send(HttpMethodEnum::POST, self::CARTS_PATH);
        $cartId = $this->rememberCart($response);

        self::assertSame(ResponseStatusEnum::CREATED->value, $response->getStatusCode());
        self::assertSame(
            sprintf(self::CART_PATH_FORMAT, $cartId),
            $response->getHeaderLine(HeaderNameEnum::LOCATION->value),
        );
        ApiClient::assertMatchesSpecification(self::CARTS_PATH, HttpMethodEnum::POST->value, $response);
    }

    public function testSetsItemQuantitiesAndReportsTotals(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
        $cartId = $this->rememberCart(ApiClient::send(HttpMethodEnum::POST, self::CARTS_PATH));
        $itemPath = sprintf(self::ITEM_PATH_FORMAT, $cartId, $productId);

        $updated = ApiClient::send(HttpMethodEnum::PUT, $itemPath, ['quantity' => 3]);
        $shown = ApiClient::get(sprintf(self::CART_PATH_FORMAT, $cartId));

        ApiClient::assertMatchesSpecification(self::ITEM_SPECIFICATION_PATH, HttpMethodEnum::PUT->value, $updated);
        ApiClient::assertMatchesSpecification(self::CART_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $shown);
        self::assertSame(
            ['amount' => (string) (3 * self::PRICE_IN_GROSZE), 'currency' => 'PLN'],
            ApiClient::json($shown)['total'],
        );
    }

    public function testRejectsInvalidChangesWithProblemDetails(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
        $cartId = $this->rememberCart(ApiClient::send(HttpMethodEnum::POST, self::CARTS_PATH));
        $itemPath = sprintf(self::ITEM_PATH_FORMAT, $cartId, $productId);

        $tooMany = ApiClient::send(HttpMethodEnum::PUT, $itemPath, ['quantity' => 1000]);
        $notInteger = ApiClient::send(HttpMethodEnum::PUT, $itemPath, ['quantity' => 'three']);
        $missingProduct = ApiClient::send(HttpMethodEnum::PUT, sprintf(self::ITEM_PATH_FORMAT, $cartId, PHP_INT_MAX), ['quantity' => 1]);

        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, $tooMany->getStatusCode());
        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $notInteger->getStatusCode());
        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $missingProduct->getStatusCode());
        ApiClient::assertMatchesSpecification(self::ITEM_SPECIFICATION_PATH, HttpMethodEnum::PUT->value, $tooMany);
    }

    public function testUnknownCartIsNotFound(): void
    {
        $response = ApiClient::get(sprintf(self::CART_PATH_FORMAT, self::MISSING_CART_ID));

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $response->getStatusCode());
        ApiClient::assertMatchesSpecification(self::CART_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $response);
    }

    private function rememberCart(ResponseInterface $response): string
    {
        $id = ApiClient::json($response)['id'] ?? null;
        self::assertIsString($id);
        $this->cartIds[] = CartId::fromString($id);

        return $id;
    }
}
