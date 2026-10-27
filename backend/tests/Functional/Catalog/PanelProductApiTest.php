<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\SessionApiClient;
use Tests\Functional\Support\TestProductData;
use Tests\Functional\Support\TestUserData;

/**
 * The merchant panel edits a product's price and stock with optimistic locking, replacing
 * the retired legacy stock page.
 */
final class PanelProductApiTest extends TestCase
{
    private const string PANEL_PRODUCT_PATH = '/api/v1/panel/products/%d';
    private const string PUBLIC_PRODUCT_PATH = '/api/v1/products/%d';
    private const string SPEC_PATH = '/api/v1/panel/products/{id}';
    private const string SESSION_PATH = '/api/v1/session';
    private const string SKU_PREFIX = 'panel-edit-';
    private const string EMAIL_PREFIX = 'panel-edit-';
    private const string PASSWORD = 'functional-password-123';
    private const string CREDENTIALS_FORMAT = '{"email":"%s","password":"%s"}';
    private const string EDIT_FORMAT = '{"price":{"amount":"%s","currency":"PLN"},"stock":%d}';
    private const string ETAG_HEADER = 'ETag';
    private const string IF_MATCH_HEADER = 'If-Match';
    private const int CACHE_WAIT_TIMEOUT_SECONDS = 15;
    private const int CACHE_POLL_INTERVAL_MICROSECONDS = 100000;

    private PDO $pdo;
    private SessionApiClient $client;
    private string $prefix;
    private string $email;
    private int $productId;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->client = new SessionApiClient();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
        [$this->productId] = TestProductData::create($this->pdo, $this->prefix, 1, 1999, 10, 3);
    }

    protected function tearDown(): void
    {
        TestProductData::delete($this->pdo, $this->prefix);
        TestUserData::delete($this->pdo, $this->email);
    }

    public function testRequiresASignedInMerchant(): void
    {
        $response = $this->client->get($this->path());

        $this->assertResponse(HttpMethodEnum::GET, ResponseStatusEnum::UNAUTHORIZED, $response);
    }

    public function testEditsWithOptimisticLockingAndRefreshesTheCatalogCache(): void
    {
        $this->signIn();
        ApiClient::get(sprintf(self::PUBLIC_PRODUCT_PATH, $this->productId));
        $read = $this->client->get($this->path());
        $this->assertResponse(HttpMethodEnum::GET, ResponseStatusEnum::OK, $read);
        $etag = $read->getHeaderLine(self::ETAG_HEADER);

        $withoutPrecondition = $this->client->send(HttpMethodEnum::PATCH, $this->path(), sprintf(self::EDIT_FORMAT, '2500', 12));
        $belowReservations = $this->client->send(HttpMethodEnum::PATCH, $this->path(), sprintf(self::EDIT_FORMAT, '2500', 2), [self::IF_MATCH_HEADER => $etag]);
        $edited = $this->client->send(HttpMethodEnum::PATCH, $this->path(), sprintf(self::EDIT_FORMAT, '2500', 12), [self::IF_MATCH_HEADER => $etag]);
        $stale = $this->client->send(HttpMethodEnum::PATCH, $this->path(), sprintf(self::EDIT_FORMAT, '3000', 12), [self::IF_MATCH_HEADER => $etag]);

        $this->assertResponse(HttpMethodEnum::PATCH, ResponseStatusEnum::PRECONDITION_REQUIRED, $withoutPrecondition);
        $this->assertResponse(HttpMethodEnum::PATCH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $belowReservations);
        $body = $this->assertResponse(HttpMethodEnum::PATCH, ResponseStatusEnum::OK, $edited);
        $this->assertResponse(HttpMethodEnum::PATCH, ResponseStatusEnum::PRECONDITION_FAILED, $stale);
        self::assertSame(12, $body['stock']);
        self::assertNotSame($etag, $edited->getHeaderLine(self::ETAG_HEADER));
        self::assertSame('2500', $this->publicPriceAfterInvalidation());
    }

    private function signIn(): void
    {
        TestUserData::createMerchant($this->pdo, $this->email, self::PASSWORD);
        $response = $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, sprintf(self::CREDENTIALS_FORMAT, $this->email, self::PASSWORD));
        self::assertSame(ResponseStatusEnum::OK->value, $response->getStatusCode());
    }

    /**
     * The public catalog reads products through the Redis cache, so it shows the new price only
     * after the worker handled the change recorded in the outbox.
     */
    private function publicPriceAfterInvalidation(): ?string
    {
        $deadline = microtime(true) + self::CACHE_WAIT_TIMEOUT_SECONDS;

        do {
            $price = ApiClient::json(ApiClient::get(sprintf(self::PUBLIC_PRODUCT_PATH, $this->productId)))['price'] ?? null;
            $amount = is_array($price) ? ($price['amount'] ?? null) : null;

            if ($amount !== '1999') {
                return is_string($amount) ? $amount : null;
            }

            usleep(self::CACHE_POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        return '1999';
    }

    private function path(): string
    {
        return sprintf(self::PANEL_PRODUCT_PATH, $this->productId);
    }

    /** @return array<string, mixed> */
    private function assertResponse(HttpMethodEnum $method, ResponseStatusEnum $status, ResponseInterface $response): array
    {
        self::assertSame($status->value, $response->getStatusCode(), (string) $response->getBody());
        ApiClient::assertMatchesSpecification(self::SPEC_PATH, $method->value, $response);

        return ApiClient::json($response);
    }
}
