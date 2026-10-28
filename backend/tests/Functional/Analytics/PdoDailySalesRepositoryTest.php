<?php

declare(strict_types=1);

namespace Tests\Functional\Analytics;

use App\Analytics\Repository\PdoDailySalesRepository;
use App\Cart\Entity\CartId;
use App\Order\Entity\OrderId;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\SqlPlaceholders;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestOrderData;
use Tests\Functional\Support\TestProductData;

final class PdoDailySalesRepositoryTest extends TestCase
{
    private const string SKU_PREFIX = 'daily-sales-';
    private const string DAY = '2001-07-14';
    private const string PAID = 'paid';
    private const string CANCELLED = 'cancelled';
    private const string SUMMARY_QUERY_FORMAT = <<<'SQL'
        SELECT day, product_id AS productId, currency, units, revenue
        FROM daily_product_sales
        WHERE product_id IN (%s)
        ORDER BY day, product_id
        SQL;
    private const string PUBLIC_ID_QUERY = 'SELECT BIN_TO_UUID(public_id) FROM orders WHERE cart_id = :cart_id';

    private PDO $pdo;
    private PdoDailySalesRepository $sales;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];
    /** @var list<int> */
    private array $productIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->sales = new PdoDailySalesRepository($this->pdo);
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
        $this->productIds = TestProductData::create($this->pdo, $this->prefix, 2, 1000, 100);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testSumsThePaidOrdersOfTheDayPerProduct(): void
    {
        [$mug, $bowl] = $this->productIds;
        $this->order(self::PAID, self::DAY . ' 09:00:00', [[$mug, 2, 1500]]);
        $this->order(self::PAID, self::DAY . ' 23:59:59', [[$mug, 1, 1500], [$bowl, 3, 1000]]);
        $this->order(self::CANCELLED, self::DAY . ' 12:00:00', [[$mug, 10, 1500]]);
        $this->order(self::PAID, '2001-07-15 00:00:00', [[$bowl, 5, 1000]]);

        $this->sales->refreshDay(new DateTimeImmutable(self::DAY));

        self::assertSame([
            [self::DAY, $mug, 'PLN', 3, 4500],
            [self::DAY, $bowl, 'PLN', 3, 3000],
        ], $this->summary());
    }

    public function testRefreshingAgainReplacesTheDayInsteadOfAddingToIt(): void
    {
        [$mug] = $this->productIds;
        $this->order(self::PAID, self::DAY . ' 09:00:00', [[$mug, 2, 1500]]);
        $this->sales->refreshDay(new DateTimeImmutable(self::DAY));
        $this->sales->refreshDay(new DateTimeImmutable(self::DAY));
        self::assertSame([[self::DAY, $mug, 'PLN', 2, 3000]], $this->summary());

        $this->order(self::PAID, self::DAY . ' 18:00:00', [[$mug, 1, 1500]]);
        $this->sales->refreshDay(new DateTimeImmutable(self::DAY));

        self::assertSame([[self::DAY, $mug, 'PLN', 3, 4500]], $this->summary());
    }

    public function testFindsTheDayAnOrderWasPlacedOn(): void
    {
        [$mug] = $this->productIds;
        $cartId = $this->order(self::PAID, self::DAY . ' 23:30:00', [[$mug, 1, 1500]]);
        $statement = $this->pdo->prepare(self::PUBLIC_ID_QUERY);
        $statement->execute(['cart_id' => $cartId->toBinary()]);

        $day = $this->sales->findPlacementDay(OrderId::fromString((string) $statement->fetchColumn()));

        self::assertSame(self::DAY, $day?->format('Y-m-d'));
        self::assertNull($this->sales->findPlacementDay(OrderId::generate()));
    }

    /** @param list<array{int, int, int}> $lines */
    private function order(string $status, string $createdAt, array $lines): CartId
    {
        $cartId = TestOrderData::insert($this->pdo, $status, $createdAt, $lines);
        $this->cartIds[] = $cartId;

        return $cartId;
    }

    /** @return array<array{string, int, string, int, int}> */
    private function summary(): array
    {
        $statement = $this->pdo->prepare(sprintf(self::SUMMARY_QUERY_FORMAT, SqlPlaceholders::positional(count($this->productIds))));
        $statement->execute($this->productIds);

        return array_map(
            static fn (array $row): array => [(string) $row['day'], (int) $row['productId'], (string) $row['currency'], (int) $row['units'], (int) $row['revenue']],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }
}
