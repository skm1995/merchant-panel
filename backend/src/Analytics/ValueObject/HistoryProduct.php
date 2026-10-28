<?php

declare(strict_types=1);

namespace App\Analytics\ValueObject;

use Money\Money;

final readonly class HistoryProduct
{
    public function __construct(
        public string $sku,
        public string $name,
        public Money $price,
        public int $stock,
    ) {
    }
}
