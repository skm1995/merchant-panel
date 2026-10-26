<?php

declare(strict_types=1);

namespace App\Order\Entity;

use Money\Money;

/**
 * Product snapshot frozen at checkout, so later catalog changes do not alter the order.
 */
final readonly class OrderLine
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
