<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\ReadModel\OrderLineView;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<OrderLineView> */
#[HydratedRecord]
final readonly class OrderLineViewRecord implements EntityRecord
{
    public int $productId;
    public string $sku;
    public string $name;
    public int $quantity;
    public int $unitPriceAmount;
    /** @var non-empty-string */
    public string $unitPriceCurrency;

    public function toEntity(): OrderLineView
    {
        return new OrderLineView(
            $this->productId,
            $this->sku,
            $this->name,
            $this->quantity,
            new Money($this->unitPriceAmount, new Currency($this->unitPriceCurrency)),
        );
    }
}
