<?php

declare(strict_types=1);

namespace Tests\Functional\Report;

use App\Cart\Entity\CartId;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestOrderData;
use Tests\Functional\Support\TestProductData;

/**
 * Golden master of the order report's observable content, recorded from the legacy script
 * before it was refactored into the Report module and kept unchanged since. Order ids are replaced by stable placeholders. Delete the snapshot to
 * record it again, and review the diff before committing it.
 */
final class OrderReportGoldenMasterTest extends TestCase
{
    private const string REPORT_PATH = '/legacy/order-report.php';
    private const string SNAPSHOT_PATH = '/__snapshots__/order-report.json';
    private const string SKU_PREFIX = 'legacy-';
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';
    private const string API_KEY_HEADER = 'X-Api-Key';
    private const string PAID = 'paid';
    private const string CANCELLED = 'cancelled';
    private const string SINCE = '1999-01-01';
    private const string ORDER_ID_QUERY = 'SELECT id FROM orders WHERE cart_id = :cart_id';
    private const string OTHER_PAID_ORDERS_QUERY = "SELECT COUNT(*) FROM orders WHERE status = 'paid' AND created_at >= '1999-01-01'";
    private const string PLACEHOLDER_FORMAT = '#%d';

    private PDO $pdo;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
        self::assertSame(0, $this->countRows(self::OTHER_PAID_ORDERS_QUERY), 'Other paid orders would change the report.');
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testReportContentMatchesTheRecordedGoldenMaster(): void
    {
        [$mug, $plate, $bowl] = TestProductData::create($this->pdo, $this->prefix, 3, 100, 100);
        $placeholders = $this->order(self::PAID, '1999-05-01 10:00:00', [[$mug, 1, 123456, 'Mug']])
            + $this->order(self::PAID, '1999-05-02 11:30:00', [[$plate, 2, 1999, 'Plate'], [$bowl, 1, 550, 'Bowl']])
            + $this->order(self::PAID, '1999-05-02 11:30:00', [])
            + $this->order(self::CANCELLED, '1999-05-03 09:00:00', [[$mug, 1, 1000, 'Mug']]);

        $content = $this->reportContent(['status' => self::PAID, 'since' => self::SINCE], $placeholders);

        $snapshot = __DIR__ . self::SNAPSHOT_PATH;

        if (!is_file($snapshot)) {
            file_put_contents($snapshot, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
            self::markTestIncomplete('Recorded a new golden master; review and commit it.');
        }

        self::assertSame(json_decode((string) file_get_contents($snapshot), true, flags: JSON_THROW_ON_ERROR), $content);
    }

    /**
     * @param array<string, string> $query
     * @param array<int, string> $placeholders
     * @return array{heading: string, rows: list<list<string>>, summary: string}
     */
    private function reportContent(array $query, array $placeholders): array
    {
        $response = ApiClient::sendWithQuery(self::REPORT_PATH, $query, [
            self::API_KEY_HEADER => Environment::get(self::API_KEY_VARIABLE),
        ]);
        $page = new Crawler((string) $response->getBody());
        $rows = $page->filter('tr')->each(static fn (Crawler $row): array => $row->filter('th, td')->each(
            static fn (Crawler $cell): string => trim($cell->text()),
        ));

        return [
            'heading' => $page->filter('h1')->text(),
            'rows' => array_map(
                static fn (array $cells): array => array_map(
                    static fn (string $cell): string => $placeholders[(int) $cell] ?? $cell,
                    $cells,
                ),
                $rows,
            ),
            'summary' => $page->filter('p')->last()->text(),
        ];
    }

    /**
     * @param list<array{0: int, 1: int, 2: int, 3?: string}> $lines
     * @return array<int, string> placeholder keyed by order id
     */
    private function order(string $status, string $createdAt, array $lines): array
    {
        $cartId = TestOrderData::insert($this->pdo, $status, $createdAt, $lines);
        $this->cartIds[] = $cartId;
        $statement = $this->pdo->prepare(self::ORDER_ID_QUERY);
        $statement->execute(['cart_id' => $cartId->toBinary()]);

        return [(int) $statement->fetchColumn() => sprintf(self::PLACEHOLDER_FORMAT, count($this->cartIds))];
    }

    private function countRows(string $query): int
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
