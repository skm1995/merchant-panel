<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

final readonly class HeatmapCell
{
    /**
     * @param int $weekday ISO weekday, 1 is Monday
     * @param int $hour hour in UTC
     */
    public function __construct(public int $weekday, public int $hour, public int $orders)
    {
    }
}
