<?php

declare(strict_types=1);

namespace App\Report\Controller;

use App\Report\Enum\ReportEnvironmentEnum;
use App\Report\Request\ReportLimitInput;
use App\Report\Request\ReportPeriodInput;
use App\Report\Request\TopProductsRequest;
use App\Report\Response\DailyRevenueResponse;
use App\Report\Response\MonthlyRevenueResponse;
use App\Report\Response\OrderHeatmapResponse;
use App\Report\Response\StockOutlookResponse;
use App\Report\Response\TopProductsResponse;
use App\Report\Service\ReportService;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\RequireMerchant;
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
    private const string STOCK_OUTLOOK_ROUTE_PATH = '/api/v1/reports/stock-outlook';
    private const string ORDER_HEATMAP_ROUTE_PATH = '/api/v1/reports/order-heatmap';
    private const string MONTHLY_REVENUE_ROUTE_PATH = '/api/v1/reports/monthly-revenue';
    private const string PANEL_STOCK_OUTLOOK_ROUTE_PATH = '/api/v1/panel/reports/stock-outlook';
    private const string PANEL_ORDER_HEATMAP_ROUTE_PATH = '/api/v1/panel/reports/order-heatmap';
    private const string PANEL_MONTHLY_REVENUE_ROUTE_PATH = '/api/v1/panel/reports/monthly-revenue';

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
    #[RequireMerchant]
    public function panelDailyRevenue(Request $request): void
    {
        $this->respondWithDailyRevenue($request);
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_TOP_PRODUCTS_ROUTE_PATH)]
    #[RequireMerchant]
    public function panelTopProducts(Request $request): void
    {
        $this->respondWithTopProducts($request);
    }

    #[Route(HttpMethodEnum::GET, self::STOCK_OUTLOOK_ROUTE_PATH)]
    #[RequireApiKey(ReportEnvironmentEnum::API_KEY->value)]
    public function stockOutlook(Request $request): void
    {
        $this->respondWithStockOutlook($request);
    }

    #[Route(HttpMethodEnum::GET, self::ORDER_HEATMAP_ROUTE_PATH)]
    #[RequireApiKey(ReportEnvironmentEnum::API_KEY->value)]
    public function orderHeatmap(Request $request): void
    {
        $this->respondWithOrderHeatmap($request);
    }

    #[Route(HttpMethodEnum::GET, self::MONTHLY_REVENUE_ROUTE_PATH)]
    #[RequireApiKey(ReportEnvironmentEnum::API_KEY->value)]
    public function monthlyRevenue(Request $request): void
    {
        $this->respondWithMonthlyRevenue($request);
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_STOCK_OUTLOOK_ROUTE_PATH)]
    #[RequireMerchant]
    public function panelStockOutlook(Request $request): void
    {
        $this->respondWithStockOutlook($request);
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_ORDER_HEATMAP_ROUTE_PATH)]
    #[RequireMerchant]
    public function panelOrderHeatmap(Request $request): void
    {
        $this->respondWithOrderHeatmap($request);
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_MONTHLY_REVENUE_ROUTE_PATH)]
    #[RequireMerchant]
    public function panelMonthlyRevenue(Request $request): void
    {
        $this->respondWithMonthlyRevenue($request);
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

    private function respondWithStockOutlook(Request $request): void
    {
        $this->responder->json(new StockOutlookResponse(ReportService::OUTLOOK_DAYS, $this->reports->stockOutlook(ReportLimitInput::parse($request))));
    }

    private function respondWithOrderHeatmap(Request $request): void
    {
        $period = ReportPeriodInput::parse($request);

        $this->responder->json(new OrderHeatmapResponse($period, $this->reports->orderHeatmap($period)));
    }

    private function respondWithMonthlyRevenue(Request $request): void
    {
        $currency = ReportPeriodInput::currency($request);

        $this->responder->json(new MonthlyRevenueResponse($currency, $this->reports->monthlyRevenue($currency)));
    }
}
