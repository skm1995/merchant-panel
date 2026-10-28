<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

final readonly class StockOutlook
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $available,
        public int $unitsSold,
        public float $unitsPerDay,
        public int $daysLeft,
    ) {
    }
}
