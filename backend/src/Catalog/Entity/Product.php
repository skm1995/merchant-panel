<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Exception\StockBelowReservationsException;
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

    /**
     * The product with a new price and stock; the stock must still cover the reserved units.
     */
    public function withPriceAndStock(Money $price, int $stock): self
    {
        if ($stock < $this->reserved) {
            throw new StockBelowReservationsException($this->reserved);
        }

        return new self($this->id, $this->sku, $this->name, $price, $stock, $this->reserved, $this->version);
    }

    public function availableQuantity(): int
    {
        return $this->stock - $this->reserved;
    }
}
