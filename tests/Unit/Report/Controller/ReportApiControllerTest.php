<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Controller;

use App\Report\Controller\ReportApiController;
use App\Report\Query\DailyRevenueHandler;
use App\Report\Query\TopProductsHandler;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportApiControllerTest extends TestCase
{
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';
    private const string API_KEY = 'unit-test-reports-key';
    private const string DAILY_REVENUE_PATH = '/api/v1/reports/daily-revenue';
    private const string TOP_PRODUCTS_PATH = '/api/v1/reports/top-products';

    private string|false $originalKey;

    protected function setUp(): void
    {
        $this->originalKey = getenv(self::API_KEY_VARIABLE);
        putenv(self::API_KEY_VARIABLE . '=' . self::API_KEY);
    }

    protected function tearDown(): void
    {
        putenv($this->originalKey === false ? self::API_KEY_VARIABLE : self::API_KEY_VARIABLE . '=' . $this->originalKey);
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testReportsRequireTheApiKey(): void
    {
        $reports = $this->createMock(ReportReadRepositoryInterface::class);
        $reports->expects(self::never())->method('dailyRevenue');

        $this->dispatch(self::DAILY_REVENUE_PATH, [], $reports);

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
    }

    public function testReturnsReportsForAuthorizedClients(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('dailyRevenue')->willReturn([ReportFixtures::day(ReportFixtures::FROM, 1, 100, 100)]);
        $reports->method('topProducts')->willReturn([ReportFixtures::product(1, 7, 1, 100)]);
        $headers = ['x-api-key' => self::API_KEY];

        $daily = $this->dispatch(self::DAILY_REVENUE_PATH, $headers, $reports);
        $top = $this->dispatch(self::TOP_PRODUCTS_PATH, $headers, $reports);

        self::assertSame(1, $daily['days'][0]['orders']);
        self::assertSame(7, $top['products'][0]['product_id']);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function dispatch(string $path, array $headers, ReportReadRepositoryInterface $reports): array
    {
        $responder = new Responder(new CsrfToken(new Session()));
        $router = new Router(new CsrfToken(new Session()), $responder);
        $router->registerController(new ReportApiController(
            new DailyRevenueHandler($reports),
            new TopProductsHandler($reports),
            $responder,
        ));
        ob_start();
        $router->dispatch(new Request(
            HttpMethodEnum::GET->value,
            $path,
            ['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO],
            headers: $headers,
        ));
        $output = ob_get_clean();
        self::assertIsString($output);
        $body = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
