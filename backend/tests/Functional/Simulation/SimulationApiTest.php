<?php

declare(strict_types=1);

namespace Tests\Functional\Simulation;

use App\Shared\Database\DatabaseConnection;
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
