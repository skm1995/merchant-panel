<?php

declare(strict_types=1);

namespace App\Report\Controller;

use App\Report\Enum\ReportEnvironmentEnum;
use App\Report\Request\OrderReportRequest;
use App\Report\Response\OrderReportPage;
use App\Report\Service\ReportService;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

/**
 * Serves the order report at the URL of the legacy script it replaced, so existing links keep working.
 */
final class OrderReportController
{
    private const string ROUTE_PATH = '/legacy/order-report.php';
    private const string TEMPLATE = 'report/order-report';

    public function __construct(
        private readonly ReportService $reports,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::ROUTE_PATH)]
    #[RequireApiKey(ReportEnvironmentEnum::API_KEY->value)]
    public function show(Request $request): void
    {
        $query = OrderReportRequest::fromRequest($request);
        $orders = $this->reports->orders($query->status, $query->since);

        $this->responder->render(self::TEMPLATE, OrderReportPage::fromOrders($query->status, $query->since, $orders)->toArray());
    }
}
