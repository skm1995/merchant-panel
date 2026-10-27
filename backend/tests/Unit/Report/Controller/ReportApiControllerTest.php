<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Controller;

use App\Report\Controller\ReportApiController;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Report\Service\ReportService;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Report\ReportFixtures;

final class ReportApiControllerTest extends TestCase
{
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';
    private const string API_KEY = 'unit-test-reports-key';
    private const string DAILY_REVENUE_PATH = '/api/v1/reports/daily-revenue';
    private const string TOP_PRODUCTS_PATH = '/api/v1/reports/top-products';
    private const string PANEL_TOP_PRODUCTS_PATH = '/api/v1/panel/reports/top-products';

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
        $reports->expects(self::never())->method('dailySales');

        $this->dispatch(self::DAILY_REVENUE_PATH, [], $reports);

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
    }

    public function testPanelReportsRequireASignedInMerchant(): void
    {
        $reports = $this->createMock(ReportReadRepositoryInterface::class);
        $reports->expects(self::never())->method('bestSellingProducts');

        $this->dispatch(self::PANEL_TOP_PRODUCTS_PATH, [], $reports);

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
    }

    public function testReturnsReportsForAuthorizedClients(): void
    {
        $reports = $this->createStub(ReportReadRepositoryInterface::class);
        $reports->method('dailySales')->willReturn([ReportFixtures::sales(ReportFixtures::FROM, 1, 100)]);
        $reports->method('bestSellingProducts')->willReturn([ReportFixtures::productSales(7, 1, 100)]);
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
        return HttpHarness::json(
            new Request(HttpMethodEnum::GET->value, $path, ['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO], headers: $headers),
            new ReportApiController(new ReportService($reports), HttpHarness::responder()),
        );
    }
}
