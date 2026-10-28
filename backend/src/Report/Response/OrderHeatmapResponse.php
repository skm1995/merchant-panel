<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\ReadModel\HeatmapCell;
use App\Report\ValueObject\ReportDate;
use App\Report\ValueObject\ReportPeriod;
use App\Shared\Contract\Arrayable;

final readonly class OrderHeatmapResponse implements Arrayable
{
    private const string FROM_KEY = 'from';
    private const string TO_KEY = 'to';
    private const string CELLS_KEY = 'cells';
    private const string WEEKDAY_KEY = 'weekday';
    private const string HOUR_KEY = 'hour';
    private const string ORDERS_KEY = 'orders';

    /** @param list<HeatmapCell> $cells */
    public function __construct(public ReportPeriod $period, public array $cells)
    {
    }

    public function toArray(): array
    {
        return [
            self::FROM_KEY => ReportDate::format($this->period->from),
            self::TO_KEY => ReportDate::format($this->period->to),
            self::CELLS_KEY => array_map(static fn (HeatmapCell $cell): array => [
                self::WEEKDAY_KEY => $cell->weekday,
                self::HOUR_KEY => $cell->hour,
                self::ORDERS_KEY => $cell->orders,
            ], $this->cells),
        ];
    }
}
