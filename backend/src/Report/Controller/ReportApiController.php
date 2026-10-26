<?php

declare(strict_types=1);

namespace App\Report\Controller;

use App\Report\Enum\ReportEnvironmentEnum;
use App\Report\Request\ReportPeriodInput;
use App\Report\Request\TopProductsRequest;
use App\Report\Response\DailyRevenueResponse;
use App\Report\Response\TopProductsResponse;
use App\Report\Service\ReportService;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\RequireSignedIn;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

/**
 * The same reports for integrations, with the API key, and for the merchant panel, with the
 * session of a signed-in merchant.
 */
final readonly class ReportApiController
{
    private const string DAILY_REVENUE_ROUTE_PATH = '/api/v1/reports/daily-revenue';
    private const string TOP_PRODUCTS_ROUTE_PATH = '/api/v1/reports/top-products';
    private const string PANEL_DAILY_REVENUE_ROUTE_PATH = '/api/v1/panel/reports/daily-revenue';
    private const string PANEL_TOP_PRODUCTS_ROUTE_PATH = '/api/v1/panel/reports/top-products';

    public function __construct(
        private ReportService $reports,
        private Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::DAILY_REVENUE_ROUTE_PATH)]
    #[RequireApiKey(ReportEnvironmentEnum::API_KEY->value)]
    public function dailyRevenue(Request $request): void
    {
        $this->respondWithDailyRevenue($request);
    }

    #[Route(HttpMethodEnum::GET, self::TOP_PRODUCTS_ROUTE_PATH)]
    #[RequireApiKey(ReportEnvironmentEnum::API_KEY->value)]
    public function topProducts(Request $request): void
    {
        $this->respondWithTopProducts($request);
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_DAILY_REVENUE_ROUTE_PATH)]
    #[RequireSignedIn]
    public function panelDailyRevenue(Request $request): void
    {
        $this->respondWithDailyRevenue($request);
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_TOP_PRODUCTS_ROUTE_PATH)]
    #[RequireSignedIn]
    public function panelTopProducts(Request $request): void
    {
        $this->respondWithTopProducts($request);
    }

    private function respondWithDailyRevenue(Request $request): void
    {
        $period = ReportPeriodInput::parse($request);

        $this->responder->json(new DailyRevenueResponse($period, $this->reports->dailyRevenue($period)));
    }

    private function respondWithTopProducts(Request $request): void
    {
        $query = TopProductsRequest::fromRequest($request);

        $this->responder->json(new TopProductsResponse($query->period, $this->reports->topProducts($query->period, $query->limit)));
    }
}
