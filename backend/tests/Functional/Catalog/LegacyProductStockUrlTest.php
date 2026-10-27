<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Tests\Functional\Support\Browser;
use Tests\Functional\Support\TestProductData;

/**
 * The retired product stock page was characterized by a golden master before it was
 * replaced. As reviewed, its URL now only redirects old links to the panel's product
 * editor, and none of the inputs that used to reach its SQL can change data any more.
 */
final class LegacyProductStockUrlTest extends TestCase
{
    private const string PAGE_PATH = '/legacy/product-stock.php';
    private const string EDITOR_PATH_FORMAT = '/products/%d/edit';
    private const string SKU_PREFIX = 'legacy-stock-';
    private const string LOCATION_HEADER = 'location';
    private const string PRODUCT_QUERY = 'SELECT price_amount, stock, version FROM products WHERE id = :id';

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

    public function testOldLinksRedirectToTheProductEditor(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, 1999, 10);

        $response = HttpClient::create(['max_redirects' => 0])
            ->request(HttpMethodEnum::GET->value, Browser::url(self::PAGE_PATH . '?id=' . $productId));

        self::assertSame(ResponseStatusEnum::MOVED_PERMANENTLY->value, $response->getStatusCode());
        self::assertSame([sprintf(self::EDITOR_PATH_FORMAT, $productId)], $response->getHeaders(false)[self::LOCATION_HEADER] ?? null);
    }

    public function testInputsThatReachedTheOldSqlChangeNothing(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, 1999, 10);
        $before = $this->stored($productId);
        $client = HttpClient::create(['max_redirects' => 0]);

        $injected = $client->request(HttpMethodEnum::GET->value, Browser::url(self::PAGE_PATH), [
            'query' => ['id' => $productId . ' OR 1=1'],
        ]);
        $unauthenticatedWrite = $client->request(HttpMethodEnum::POST->value, Browser::url(self::PAGE_PATH . '?id=' . $productId), [
            'body' => ['price' => '1', 'stock' => '0'],
        ]);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $injected->getStatusCode());
        self::assertSame(ResponseStatusEnum::METHOD_NOT_ALLOWED->value, $unauthenticatedWrite->getStatusCode(), 'The retired page only redirects; it accepts no writes.');
        self::assertSame($before, $this->stored($productId));
    }

    /** @return array<string, mixed> */
    private function stored(int $productId): array
    {
        $statement = $this->pdo->prepare(self::PRODUCT_QUERY);
        $statement->execute(['id' => $productId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
