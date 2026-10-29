<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog;

use App\Catalog\Service\ProductCatalog;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestProductData;

final class ProductApiTest extends TestCase
{
    private const string SKU_PREFIX = 'api-';
    private const string PRODUCTS_PATH = '/api/v1/products';
    private const string PRODUCT_PATH_FORMAT = '/api/v1/products/%d';
    private const string PRODUCT_SPECIFICATION_PATH = '/api/v1/products/{id}';
    private const string LIMIT = 'limit';
    private const string CURSOR = 'cursor';
    private const string INVALID_LIMIT = 'abc';
    private const string NON_NUMERIC_PRODUCT_PATH = '/api/v1/products/abc';
    private const int PRICE_IN_GROSZE = 4999;
    private const int STOCK = 3;

    private PDO $pdo;
    private string $prefix;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testListsProductPagesMatchingTheContract(): void
    {
        $ids = TestProductData::create($this->pdo, $this->prefix, 3, self::PRICE_IN_GROSZE, self::STOCK);

        $first = ApiClient::get(self::PRODUCTS_PATH, [self::LIMIT => '2', self::CURSOR => (string) ($ids[0] - 1)]);
        ApiClient::assertMatchesSpecification(self::PRODUCTS_PATH, HttpMethodEnum::GET->value, $first);
        $firstBody = ApiClient::json($first);
        $second = ApiClient::get(self::PRODUCTS_PATH, [self::LIMIT => '1', self::CURSOR => (string) $firstBody['next_cursor']]);

        self::assertSame([$ids[0], $ids[1]], array_column($firstBody['data'], 'id'));
        self::assertSame($ids[2], ApiClient::json($second)['data'][0]['id']);
    }

    public function testShowsProductWithPriceInMinorUnits(): void
    {
        [$id] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);

        $response = ApiClient::get(sprintf(self::PRODUCT_PATH_FORMAT, $id));

        ApiClient::assertMatchesSpecification(self::PRODUCT_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $response);
        self::assertSame(['amount' => (string) self::PRICE_IN_GROSZE, 'currency' => 'PLN'], ApiClient::json($response)['price']);
    }

    public function testMissingProductIsNotFoundProblem(): void
    {
        $response = ApiClient::get(sprintf(self::PRODUCT_PATH_FORMAT, PHP_INT_MAX));

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $response->getStatusCode());
        self::assertSame(ContentTypeEnum::PROBLEM_JSON->value, $response->getHeaderLine(HeaderNameEnum::CONTENT_TYPE->value));
        ApiClient::assertMatchesSpecification(self::PRODUCT_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $response);
    }

    public function testNonNumericProductIdIsNotFoundProblem(): void
    {
        $response = ApiClient::get(self::NON_NUMERIC_PRODUCT_PATH);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $response->getStatusCode());
        self::assertSame(ContentTypeEnum::PROBLEM_JSON->value, $response->getHeaderLine(HeaderNameEnum::CONTENT_TYPE->value));
    }

    public function testInvalidPaginationIsBadRequestProblem(): void
    {
        $notANumber = ApiClient::get(self::PRODUCTS_PATH, [self::LIMIT => self::INVALID_LIMIT]);
        $aboveMaximum = ApiClient::get(self::PRODUCTS_PATH, [self::LIMIT => (string) (ProductCatalog::MAXIMUM_PAGE_SIZE + 1)]);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $notANumber->getStatusCode());
        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $aboveMaximum->getStatusCode());
        ApiClient::assertMatchesSpecification(self::PRODUCTS_PATH, HttpMethodEnum::GET->value, $notANumber);
        ApiClient::assertMatchesSpecification(self::PRODUCTS_PATH, HttpMethodEnum::GET->value, $aboveMaximum);
    }
}
