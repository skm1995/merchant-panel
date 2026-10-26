<?php

declare(strict_types=1);

namespace App\Order\ReadModel;

use Money\Money;

final readonly class OrderLineView
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $quantity,
        public Money $unitPrice,
    ) {
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
