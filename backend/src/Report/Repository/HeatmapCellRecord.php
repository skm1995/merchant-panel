<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\HeatmapCell;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;

/** @implements EntityRecord<HeatmapCell> */
#[HydratedRecord]
final readonly class HeatmapCellRecord implements EntityRecord
{
    public int $weekday;
    public int $hour;
    public int $orders;

    public function toEntity(): HeatmapCell
    {
        return new HeatmapCell($this->weekday, $this->hour, $this->orders);
    }
}
