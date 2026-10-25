<?php

declare(strict_types=1);

namespace App\Report\Controller;

use App\Report\Query\DailyRevenueHandler;
use App\Report\Query\TopProductsHandler;
use App\Report\Request\DailyRevenueRequest;
use App\Report\Request\TopProductsRequest;
use App\Report\Response\DailyRevenueResponse;
use App\Report\Response\TopProductsResponse;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

final class ReportApiController
{
    private const string DAILY_REVENUE_ROUTE_PATH = '/api/v1/reports/daily-revenue';
    private const string TOP_PRODUCTS_ROUTE_PATH = '/api/v1/reports/top-products';
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';

    public function __construct(
        private readonly DailyRevenueHandler $dailyRevenue,
        private readonly TopProductsHandler $topProducts,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::DAILY_REVENUE_ROUTE_PATH)]
    #[RequireApiKey(self::API_KEY_VARIABLE)]
    public function dailyRevenue(Request $request): void
    {
        $query = DailyRevenueRequest::fromRequest($request)->toQuery();

        $this->responder->json(DailyRevenueResponse::fromRows($query->period, $this->dailyRevenue->handle($query)));
    }

    #[Route(HttpMethodEnum::GET, self::TOP_PRODUCTS_ROUTE_PATH)]
    #[RequireApiKey(self::API_KEY_VARIABLE)]
    public function topProducts(Request $request): void
    {
        $query = TopProductsRequest::fromRequest($request)->toQuery();

        $this->responder->json(TopProductsResponse::fromRows($query->period, $this->topProducts->handle($query)));
    }
}
