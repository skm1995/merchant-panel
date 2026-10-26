<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Functional\Support\Browser;
use Tests\Functional\Support\TestProductData;

/**
 * Characterizes the inherited product stock page through the web server before it is
 * replaced: what it shows and what saving does.
 */
final class ProductStockPageGoldenMasterTest extends TestCase
{
    private const string PAGE_PATH = '/legacy/product-stock.php?id=%d';
    private const string SNAPSHOT_PATH = '/__snapshots__/product-stock.json';
    private const string SKU_PREFIX = 'stock-page-';
    private const string PREFIX_PLACEHOLDER = '<prefix>';
    private const string PRODUCT_QUERY = 'SELECT price_amount, stock, version FROM products WHERE id = :id';

    private PDO $pdo;
    private HttpBrowser $browser;
    private string $prefix;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->browser = Browser::create();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testPageAndSavingMatchTheRecordedGoldenMaster(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, 1999, 10, 2);
        $url = Browser::url(sprintf(self::PAGE_PATH, $productId));

        $form = $this->browser->request(HttpMethodEnum::GET->value, $url);
        $shown = $this->content($form);
        $saved = $this->browser->submitForm('Save', ['price' => '2500', 'stock' => '7']);

        $content = ['shown' => $shown, 'saved' => $this->content($saved), 'stored' => $this->stored($productId)];
        $snapshot = __DIR__ . self::SNAPSHOT_PATH;

        if (!is_file($snapshot)) {
            file_put_contents($snapshot, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
            self::markTestIncomplete('Recorded a new golden master; review and commit it.');
        }

        self::assertSame(json_decode((string) file_get_contents($snapshot), true, flags: JSON_THROW_ON_ERROR), $content);
    }

    /** @return array{heading: string, message: ?string, price: ?string, stock: ?string} */
    private function content(Crawler $page): array
    {
        $message = $page->filter('p.message');

        return [
            'heading' => str_replace($this->prefix, self::PREFIX_PLACEHOLDER, $page->filter('h1')->text()),
            'message' => $message->count() === 0 ? null : $message->text(),
            'price' => $page->filter('input[name=price]')->attr('value'),
            'stock' => $page->filter('input[name=stock]')->attr('value'),
        ];
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
