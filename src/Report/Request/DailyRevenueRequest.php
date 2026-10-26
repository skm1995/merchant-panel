<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Report\Query\DailyRevenueQuery;
use App\Shared\Http\Request;

final class DailyRevenueRequest
{
    public static function toQuery(Request $request): DailyRevenueQuery
    {
        return new DailyRevenueQuery(ReportPeriodInput::fromRequest($request));
    }
}
