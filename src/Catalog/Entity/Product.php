<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use InvalidArgumentException;
use Money\Money;

final readonly class Product
{
    private const string INVALID_STOCK_MESSAGE = 'Stock and reservations must be non-negative and reservations cannot exceed stock.';

    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public Money $price,
        public int $stock,
        public int $reserved,
        public int $version,
    ) {
        if ($stock < 0 || $reserved < 0 || $reserved > $stock) {
            throw new InvalidArgumentException(self::INVALID_STOCK_MESSAGE);
        }
    }

    public function availableQuantity(): int
    {
        return $this->stock - $this->reserved;
    }
}
