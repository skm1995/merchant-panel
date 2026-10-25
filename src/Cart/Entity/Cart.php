<?php

declare(strict_types=1);

namespace App\Cart\Entity;

use App\Cart\Enum\CartStatusEnum;
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

    /**
     * Current value of the cart at today's prices, or null for an empty cart.
     */
    public function total(): ?Money
    {
        $lineTotals = array_map(static fn (CartLine $line): Money => $line->lineTotal(), $this->lines);

        return $lineTotals === [] ? null : Money::sum(...$lineTotals);
    }
}
