<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Report\Query\DailyRevenueQuery;
use App\Report\Query\ReportPeriod;
use App\Shared\Http\Request;

final readonly class DailyRevenueRequest
{
    public function __construct(public ReportPeriod $period)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(ReportPeriodInput::fromRequest($request));
    }

    public function toQuery(): DailyRevenueQuery
    {
        return new DailyRevenueQuery($this->period);
    }
}
