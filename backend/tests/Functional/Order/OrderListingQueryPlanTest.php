<?php

declare(strict_types=1);

namespace Tests\Functional\Order;

use App\Order\Repository\PdoOrderReadRepository;
use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\QueryPlan;
use Tests\Functional\Support\TestOrderVolume;

final class OrderListingQueryPlanTest extends TestCase
{
    private const string LISTING_INDEX = 'orders_created_at';
    private const string RANGE_ACCESS = 'range';
    private const string CURSOR_CREATED_AT = '2002-06-01 00:00:00';
    private const int CURSOR_ID = 1000000;
    private const int PAGE_SIZE = 21;

    private PDO $pdo;
    private TestOrderVolume $volume;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->volume = TestOrderVolume::generate($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->volume->delete();
    }

    public function testNextPageRangeScansTheListingIndex(): void
    {
        $orders = QueryPlan::tableAccess($this->pdo, PdoOrderReadRepository::NEXT_PAGE_QUERY, [
            'created_at' => self::CURSOR_CREATED_AT,
            'same_created_at' => self::CURSOR_CREATED_AT,
            'id' => self::CURSOR_ID,
            'limit' => self::PAGE_SIZE,
        ], 'orders');

        self::assertSame(self::LISTING_INDEX, $orders['key'] ?? null);
        self::assertSame(self::RANGE_ACCESS, $orders['access_type'] ?? null);
    }
}
