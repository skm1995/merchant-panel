<?php

declare(strict_types=1);

namespace App\Cart\Repository;

use App\Cart\Entity\CartLine;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<CartLine> */
#[HydratedRecord]
final readonly class CartLineRecord implements EntityRecord
{
    public int $productId;
    public string $sku;
    public string $name;
    public int $quantity;
    public int $priceAmount;
    /** @var non-empty-string */
    public string $priceCurrency;

    public function toEntity(): CartLine
    {
        return new CartLine(
            $this->productId,
            $this->sku,
            $this->name,
            $this->quantity,
            new Money($this->priceAmount, new Currency($this->priceCurrency)),
        );
    }
}
