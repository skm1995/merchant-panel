<?php

declare(strict_types=1);

namespace Tests\Functional\Report;

use App\Cart\Entity\CartId;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestOrderData;
use Tests\Functional\Support\TestProductData;

final class OrderReportSecurityTest extends TestCase
{
    private const string REPORT_PATH = '/legacy/order-report.php';
    private const string SKU_PREFIX = 'report-security-';
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';
    private const string API_KEY_HEADER = 'X-Api-Key';
    private const string SCRIPT_NAME = '<script>alert("stored")</script>';
    private const string ESCAPED_SCRIPT_NAME = '&lt;script&gt;alert(&quot;stored&quot;)&lt;/script&gt;';
    private const string SECRET_ITEM = 'Cancelled secret';
    private const string SINCE = '1999-01-01';

    private PDO $pdo;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, 100, 100);
        $this->cartIds[] = TestOrderData::insert($this->pdo, 'paid', '1999-06-01 10:00:00', [[$productId, 1, 1000, self::SCRIPT_NAME]]);
        $this->cartIds[] = TestOrderData::insert($this->pdo, 'cancelled', '1999-06-02 10:00:00', [[$productId, 1, 1000, self::SECRET_ITEM]]);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function injectedParameters(): iterable
    {
        yield 'status' => [['status' => "paid' OR '1'='1", 'since' => self::SINCE]];
        yield 'since' => [['status' => 'paid', 'since' => "1999-01-01' OR o.status = 'cancelled' OR '1'='1"]];
    }

    /** @param array<string, string> $query */
    #[DataProvider('injectedParameters')]
    public function testSqlInjectionIsRejectedWithoutLeakingOtherOrders(array $query): void
    {
        $response = $this->report($query);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $response->getStatusCode());
        self::assertStringNotContainsString(self::SECRET_ITEM, (string) $response->getBody());
    }

    public function testStoredProductNamesAreEscaped(): void
    {
        $body = (string) $this->report(['status' => 'paid', 'since' => self::SINCE])->getBody();

        self::assertStringNotContainsString(self::SCRIPT_NAME, $body);
        self::assertStringContainsString(self::ESCAPED_SCRIPT_NAME, $body);
    }

    public function testReflectedScriptInParametersIsRejected(): void
    {
        $response = $this->report(['status' => '<script>alert("reflected")</script>', 'since' => self::SINCE]);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $response->getStatusCode());
        self::assertStringNotContainsString('<script>', (string) $response->getBody());
    }

    public function testRequiresTheReportsApiKey(): void
    {
        $response = ApiClient::sendWithQuery(self::REPORT_PATH, ['status' => 'paid'], []);

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $response->getStatusCode());
    }

    /** @param array<string, string> $query */
    private function report(array $query): ResponseInterface
    {
        return ApiClient::sendWithQuery(self::REPORT_PATH, $query, [
            self::API_KEY_HEADER => Environment::get(self::API_KEY_VARIABLE),
        ]);
    }
}
