<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Report\ValueObject\ReportPeriod;
use App\Shared\Http\Request;

final readonly class TopProductsRequest
{
    public function __construct(public ReportPeriod $period, public int $limit)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(ReportPeriodInput::parse($request), ReportLimitInput::parse($request));
    }
}
