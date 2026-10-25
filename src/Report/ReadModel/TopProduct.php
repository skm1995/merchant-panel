<?php

declare(strict_types=1);

namespace App\Report\ReadModel;

use Money\Money;

final readonly class TopProduct
{
    public function __construct(
        public int $rank,
        public int $productId,
        public string $sku,
        public string $name,
        public int $unitsSold,
        public Money $revenue,
    ) {
    }
}
