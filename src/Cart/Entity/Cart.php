<?php

declare(strict_types=1);

namespace App\Cart\Entity;

use App\Cart\Enum\CartStatusEnum;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\EmptyCartException;
use Money\Money;

final readonly class Cart
{
    /** @param list<CartLine> $lines */
    public function __construct(
        public CartId $id,
        public CartStatusEnum $status,
        public array $lines,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->status === CartStatusEnum::OPEN;
    }

    public function assertReadyForCheckout(): void
    {
        if (!$this->isOpen()) {
            throw new CartNotOpenException();
        }

        if ($this->lines === []) {
            throw new EmptyCartException();
        }
    }

    /** @return array<int, int> quantities keyed by product id */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $quantities[$line->productId] = $line->quantity;
        }

        return $quantities;
    }

    /**
     * Current value of the cart at today's prices, or null for an empty cart.
     */
    public function total(): ?Money
    {
        $lineTotals = array_map(static fn (CartLine $line): Money => $line->lineTotal(), $this->lines);

        return $lineTotals === [] ? null : Money::sum(...$lineTotals);
    }
}
