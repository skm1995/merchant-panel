<?php

declare(strict_types=1);

namespace App\Report\Query;

use App\Report\ReadModel\ReportedOrder;
use App\Report\Repository\ReportReadRepositoryInterface;

final class OrderReportHandler
{
    public function __construct(private readonly ReportReadRepositoryInterface $reports)
    {
    }

    /** @return list<ReportedOrder> */
    public function handle(OrderReportQuery $query): array
    {
        return $this->reports->ordersByStatus($query->status, $query->since, OrderReportQuery::LIMIT);
    }
}
