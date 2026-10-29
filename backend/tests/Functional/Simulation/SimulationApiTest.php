<?php

declare(strict_types=1);

namespace Tests\Functional\Simulation;

use App\Shared\Database\DatabaseConnection;
use DateTimeImmutable;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\SessionApiClient;
use Tests\Functional\Support\TestUserData;

/**
 * A whole Black Friday simulation through the API: the test stack runs it for a few seconds
 * with a few buyers, through the real simulator, web server, worker, and database.
 */
final class SimulationApiTest extends TestCase
{
    private const string SIMULATIONS_PATH = '/api/v1/panel/simulations';
    private const string CURRENT_PATH = '/api/v1/panel/simulations/current';
    private const string SESSION_PATH = '/api/v1/session';
    private const string EMAIL_PREFIX = 'simulation-';
    private const string PASSWORD = 'functional-password-123';
    private const string CREDENTIALS_FORMAT = '{"email":"%s","password":"%s"}';
    private const int WAIT_TIMEOUT_SECONDS = 60;
    private const int POLL_INTERVAL_MICROSECONDS = 500000;
    private const string FINISHED_ORDERS_QUERY = <<<'SQL'
        SELECT id, cart_id FROM orders
        WHERE idempotency_key LIKE :key_prefix AND status <> 'pending_payment'
        SQL;
    // Carts of buyers who found the shelves empty: they hold simulation products but no order.
    private const string CARTS_WITHOUT_ORDERS_QUERY = <<<'SQL'
        SELECT carts.id FROM carts
        JOIN cart_items ON cart_items.cart_id = carts.id
        JOIN products ON products.id = cart_items.product_id
        LEFT JOIN orders ON orders.cart_id = carts.id
        WHERE products.sku LIKE 'bf-%' AND orders.id IS NULL
        SQL;
    private const string CARTS_COUNT_QUERY_FORMAT = 'SELECT COUNT(*) FROM carts WHERE id IN (%s)';
    private const string SUMMARY_UNITS_QUERY = <<<'SQL'
        SELECT COALESCE(SUM(daily_product_sales.units), 0) FROM daily_product_sales
        JOIN products ON products.id = daily_product_sales.product_id
        WHERE products.sku LIKE 'bf-%' AND daily_product_sales.day = :day
        SQL;
    private const string PAID_UNITS_QUERY = <<<'SQL'
        SELECT COALESCE(SUM(order_items.quantity), 0) FROM orders
        JOIN order_items ON order_items.order_id = orders.id
        JOIN products ON products.id = order_items.product_id
        WHERE products.sku LIKE 'bf-%' AND orders.status = 'paid'
            AND orders.created_at >= :starts_at AND orders.created_at < :ends_before
        SQL;
    private const string KEY_PREFIX_FORMAT = 'simulation:%s:%%';
    private const string DAY_FORMAT = 'Y-m-d';
    private const string NEXT_DAY = '+1 day';

    private PDO $pdo;
    private SessionApiClient $client;
    private string $email;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->client = new SessionApiClient();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);
    }

    public function testOnlyMerchantsStartAndFollowSimulations(): void
    {
        $this->assertResponse(HttpMethodEnum::POST, self::SIMULATIONS_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->send(HttpMethodEnum::POST, self::SIMULATIONS_PATH));
        $this->assertResponse(HttpMethodEnum::GET, self::CURRENT_PATH, ResponseStatusEnum::UNAUTHORIZED, $this->client->get(self::CURRENT_PATH));

        TestUserData::createVerified($this->pdo, $this->email, self::PASSWORD);
        $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, sprintf(self::CREDENTIALS_FORMAT, $this->email, self::PASSWORD));

        $this->assertResponse(HttpMethodEnum::POST, self::SIMULATIONS_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->send(HttpMethodEnum::POST, self::SIMULATIONS_PATH));
        $this->assertResponse(HttpMethodEnum::GET, self::CURRENT_PATH, ResponseStatusEnum::FORBIDDEN, $this->client->get(self::CURRENT_PATH));
    }

    public function testBuyersEmptyTheShelvesWithoutOverselling(): void
    {
        $this->signInAsMerchant();
        $this->waitUntilNoSimulationRuns();

        $started = $this->assertResponse(HttpMethodEnum::POST, self::SIMULATIONS_PATH, ResponseStatusEnum::ACCEPTED, $this->client->send(HttpMethodEnum::POST, self::SIMULATIONS_PATH));
        $refused = $this->client->send(HttpMethodEnum::POST, self::SIMULATIONS_PATH);
        $finished = $this->waitForState('finished');

        self::assertSame('running', $started['state']);
        self::assertNull($started['check']);
        self::assertIsArray($started['products']);
        self::assertNotSame([], $started['products']);
        foreach ($started['products'] as $product) {
            self::assertIsArray($product);
            self::assertSame($product['stock_at_start'], $product['stock']);
        }
        $this->assertResponse(HttpMethodEnum::POST, self::SIMULATIONS_PATH, ResponseStatusEnum::CONFLICT, $refused);
        self::assertSame($started['id'], $finished['id']);
        self::assertIsArray($finished['counters']);
        self::assertGreaterThan(0, $finished['counters']['orders_paid'], 'The buyers paid for orders.');
        self::assertIsArray($finished['check']);
        self::assertTrue($finished['check']['consistent'], 'Every unit taken from stock was paid for, and no stock went below zero.');
        self::assertSame($finished['check']['units_paid'], $finished['check']['units_sold']);
        self::assertGreaterThan(0, $finished['check']['units_sold']);
    }

    public function testTheNextSimulationRemovesWhatTheFinishedOneLeft(): void
    {
        $this->signInAsMerchant();
        $this->waitUntilNoSimulationRuns();
        $first = $this->startWhenFree();
        $this->waitForState('finished');
        $orders = $this->finishedOrdersOf($first['id']);
        $carts = [...array_column($orders, 'cart_id'), ...$this->cartsWithoutOrders()];
        self::assertNotSame([], $orders, 'The first simulation finished orders.');

        $this->startWhenFree();

        self::assertSame([], $this->finishedOrdersOf($first['id']), 'Starting a simulation removes the finished orders of the previous ones.');
        self::assertSame(0, $this->countCarts($carts), 'Their carts, and the carts of buyers who found the shelves empty, go too.');
        $this->waitForState('finished');
        $this->waitUntilTheSummaryMatchesTheOrders();
    }

    /** @return array<string, mixed> */
    private function startWhenFree(): array
    {
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $response = $this->client->send(HttpMethodEnum::POST, self::SIMULATIONS_PATH);

            if ($response->getStatusCode() === ResponseStatusEnum::ACCEPTED->value) {
                return $this->assertResponse(HttpMethodEnum::POST, self::SIMULATIONS_PATH, ResponseStatusEnum::ACCEPTED, $response);
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::fail('No simulation could start in time.');
    }

    /** @return list<array{id: int, cart_id: int}> */
    private function finishedOrdersOf(mixed $simulationId): array
    {
        self::assertIsString($simulationId);
        $statement = $this->pdo->prepare(self::FINISHED_ORDERS_QUERY);
        $statement->execute(['key_prefix' => sprintf(self::KEY_PREFIX_FORMAT, $simulationId)]);

        /** @var list<array{id: int, cart_id: int}> */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<int> */
    private function cartsWithoutOrders(): array
    {
        $statement = $this->pdo->prepare(self::CARTS_WITHOUT_ORDERS_QUERY);
        $statement->execute();

        /** @var list<int> */
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param list<int> $ids */
    private function countCarts(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $statement = $this->pdo->prepare(sprintf(self::CARTS_COUNT_QUERY_FORMAT, implode(',', array_fill(0, count($ids), '?'))));
        $statement->execute($ids);

        return (int) $statement->fetchColumn();
    }

    /**
     * The worker recomputes the days whose simulation orders were removed, so the daily sales
     * summary never counts an order that no longer exists.
     */
    private function waitUntilTheSummaryMatchesTheOrders(): void
    {
        $today = new DateTimeImmutable('today');
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $summary = $this->pdo->prepare(self::SUMMARY_UNITS_QUERY);
            $summary->execute(['day' => $today->format(self::DAY_FORMAT)]);
            $paid = $this->pdo->prepare(self::PAID_UNITS_QUERY);
            $paid->execute(['starts_at' => $today->format(self::DAY_FORMAT), 'ends_before' => $today->modify(self::NEXT_DAY)->format(self::DAY_FORMAT)]);
            $summaryUnits = (int) $summary->fetchColumn();
            $paidUnits = (int) $paid->fetchColumn();

            if ($summaryUnits === $paidUnits) {
                return;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::assertSame($paidUnits, $summaryUnits, 'The daily sales summary counts only the orders that remain.');
    }

    private function signInAsMerchant(): void
    {
        TestUserData::createMerchant($this->pdo, $this->email, self::PASSWORD);
        $response = $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, sprintf(self::CREDENTIALS_FORMAT, $this->email, self::PASSWORD));
        self::assertSame(ResponseStatusEnum::OK->value, $response->getStatusCode());
    }

    /**
     * Another run of this test may have left a simulation running in the shared test stack.
     */
    private function waitUntilNoSimulationRuns(): void
    {
        $response = $this->client->get(self::CURRENT_PATH);

        if ($response->getStatusCode() === ResponseStatusEnum::OK->value && ApiClient::json($response)['state'] === 'running') {
            $this->waitForState('finished');
        }
    }

    /** @return array<string, mixed> */
    private function waitForState(string $state): array
    {
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $simulation = $this->assertResponse(HttpMethodEnum::GET, self::CURRENT_PATH, ResponseStatusEnum::OK, $this->client->get(self::CURRENT_PATH));

            if ($simulation['state'] === $state) {
                return $simulation;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::fail(sprintf('The simulation did not reach the state "%s" in time.', $state));
    }

    /** @return array<string, mixed> */
    private function assertResponse(HttpMethodEnum $method, string $path, ResponseStatusEnum $status, ResponseInterface $response): array
    {
        self::assertSame($status->value, $response->getStatusCode(), (string) $response->getBody());
        ApiClient::assertMatchesSpecification($path, $method->value, $response);

        return ApiClient::json($response);
    }
}
