<?php

declare(strict_types=1);

namespace App\Report\Query;

final readonly class TopProductsQuery
{
    public function __construct(public ReportPeriod $period, public int $limit)
    {
    }
}
