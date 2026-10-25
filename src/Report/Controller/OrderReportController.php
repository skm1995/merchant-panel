<?php

declare(strict_types=1);

namespace App\Report\Controller;

use App\Report\Query\OrderReportHandler;
use App\Report\Request\OrderReportRequest;
use App\Report\Response\OrderReportPage;
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
    private const string API_KEY_VARIABLE = 'REPORTS_API_KEY';

    public function __construct(
        private readonly OrderReportHandler $orderReport,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::ROUTE_PATH)]
    #[RequireApiKey(self::API_KEY_VARIABLE)]
    public function show(Request $request): void
    {
        $query = OrderReportRequest::fromRequest($request)->toQuery();

        $this->responder->render(self::TEMPLATE, OrderReportPage::fromOrders($query, $this->orderReport->handle($query))->toArray());
    }
}
