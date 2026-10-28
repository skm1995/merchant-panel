<?php

declare(strict_types=1);

namespace Tests\Functional\Order;

use App\Cart\Entity\CartId;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\SessionApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestOrderData;
use Tests\Functional\Support\TestProductData;
use Tests\Functional\Support\TestUserData;

/**
 * Data the merchant panel reads with the session of a signed-in merchant.
 */
final class PanelApiTest extends TestCase
{
    private const string ORDERS_PATH = '/api/v1/panel/orders';
    private const string TOP_PRODUCTS_PATH = '/api/v1/panel/reports/top-products';
    private const string DAILY_REVENUE_PATH = '/api/v1/panel/reports/daily-revenue';
    private const string STOCK_OUTLOOK_PATH = '/api/v1/panel/reports/stock-outlook';
    private const string ORDER_HEATMAP_PATH = '/api/v1/panel/reports/order-heatmap';
    private const string MONTHLY_REVENUE_PATH = '/api/v1/panel/reports/monthly-revenue';
    private const string HEATMAP_QUERY = '?from=2037-12-01&to=2037-12-07';
    private const string SESSION_PATH = '/api/v1/session';
    private const string REPORT_QUERY = '?from=2037-12-01&to=2037-12-07';
    private const string EMAIL_PREFIX = 'panel-api-';
    private const string PRODUCT_PREFIX = 'panel-product-';
    private const string PASSWORD = 'functional-password-123';
    private const string CREDENTIALS_FORMAT = '{"email":"%s","password":"%s"}';
    /** Far in the future, so these orders are the newest whatever else the database holds. */
    private const array CREATED_AT = ['2037-12-03 10:00:00', '2037-12-02 10:00:00', '2037-12-01 10:00:00'];

    private PDO $pdo;
    private SessionApiClient $client;
    private string $email;
    private string $productPrefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->client = new SessionApiClient();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
        $this->productPrefix = TestProductData::uniquePrefix(self::PRODUCT_PREFIX);
        [$productId] = TestProductData::create($this->pdo, $this->productPrefix, 1, 1000, 100);

        foreach (self::CREATED_AT as $createdAt) {
            $this->cartIds[] = TestOrderData::insert($this->pdo, 'paid', $createdAt, [[$productId, 1, 1000]]);
        }
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->productPrefix);
        TestUserData::delete($this->pdo, $this->email);
    }

    public function testRequiresASignedInMerchant(): void
    {
        $this->assertResponse(self::ORDERS_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->get(self::ORDERS_PATH));
        $this->assertResponse(self::TOP_PRODUCTS_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->get(self::TOP_PRODUCTS_PATH . self::REPORT_QUERY));
        $this->assertResponse(self::STOCK_OUTLOOK_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->get(self::STOCK_OUTLOOK_PATH));
        $this->assertResponse(self::ORDER_HEATMAP_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->get(self::ORDER_HEATMAP_PATH . self::HEATMAP_QUERY));
        $this->assertResponse(self::MONTHLY_REVENUE_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->get(self::MONTHLY_REVENUE_PATH));
    }

    public function testRefusesSignedInUsersWithoutMerchantAccess(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::PASSWORD);
        $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, sprintf(self::CREDENTIALS_FORMAT, $this->email, self::PASSWORD));

        $this->assertResponse(self::ORDERS_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->get(self::ORDERS_PATH));
        $this->assertResponse(self::TOP_PRODUCTS_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->get(self::TOP_PRODUCTS_PATH . self::REPORT_QUERY));
        $this->assertResponse(self::STOCK_OUTLOOK_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->get(self::STOCK_OUTLOOK_PATH));
        $this->assertResponse(self::ORDER_HEATMAP_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->get(self::ORDER_HEATMAP_PATH . self::HEATMAP_QUERY));
        $this->assertResponse(self::MONTHLY_REVENUE_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->get(self::MONTHLY_REVENUE_PATH));
    }

    public function testPagesThroughOrdersNewestFirst(): void
    {
        $this->signIn();

        $first = $this->assertResponse(self::ORDERS_PATH, ResponseStatusEnum::OK, $this->client->get(self::ORDERS_PATH . '?limit=2'));
        $cursor = $first['next_cursor'];
        self::assertIsString($cursor);
        $second = $this->assertResponse(self::ORDERS_PATH, ResponseStatusEnum::OK, $this->client->get(self::ORDERS_PATH . '?limit=1&cursor=' . $cursor));

        self::assertIsArray($first['data']);
        self::assertSame(['2037-12-03T10:00:00+00:00', '2037-12-02T10:00:00+00:00'], array_column($first['data'], 'created_at'));
        self::assertIsArray($second['data']);
        self::assertSame(['2037-12-01T10:00:00+00:00'], array_column($second['data'], 'created_at'));
    }

    public function testReadsReportsWithTheSession(): void
    {
        $this->signIn();

        $revenue = $this->assertResponse(self::DAILY_REVENUE_PATH, ResponseStatusEnum::OK, $this->client->get(self::DAILY_REVENUE_PATH . self::REPORT_QUERY));
        $this->assertResponse(self::TOP_PRODUCTS_PATH, ResponseStatusEnum::OK, $this->client->get(self::TOP_PRODUCTS_PATH . self::REPORT_QUERY));
        $this->assertResponse(self::STOCK_OUTLOOK_PATH, ResponseStatusEnum::OK, $this->client->get(self::STOCK_OUTLOOK_PATH));
        $this->assertResponse(self::ORDER_HEATMAP_PATH, ResponseStatusEnum::OK, $this->client->get(self::ORDER_HEATMAP_PATH . self::HEATMAP_QUERY));
        $this->assertResponse(self::MONTHLY_REVENUE_PATH, ResponseStatusEnum::OK, $this->client->get(self::MONTHLY_REVENUE_PATH));

        self::assertIsArray($revenue['days']);
        self::assertCount(3, $revenue['days']);
    }

    private function signIn(): void
    {
        TestUserData::createMerchant($this->pdo, $this->email, self::PASSWORD);
        $response = $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, sprintf(self::CREDENTIALS_FORMAT, $this->email, self::PASSWORD));
        self::assertSame(ResponseStatusEnum::OK->value, $response->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function assertResponse(string $path, ResponseStatusEnum $status, ResponseInterface $response): array
    {
        self::assertSame($status->value, $response->getStatusCode(), (string) $response->getBody());
        ApiClient::assertMatchesSpecification($path, HttpMethodEnum::GET->value, $response);

        return ApiClient::json($response);
    }
}
