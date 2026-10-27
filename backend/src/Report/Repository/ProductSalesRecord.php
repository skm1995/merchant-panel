<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\ProductSales;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<ProductSales> */
#[HydratedRecord]
final readonly class ProductSalesRecord implements EntityRecord
{
    public int $productId;
    public string $sku;
    public string $name;
    /** @var numeric-string */
    public string $unitsSold;
    /** @var numeric-string */
    public string $revenue;
    /** @var non-empty-string */
    public string $currency;

    public function toEntity(): ProductSales
    {
        return new ProductSales(
            $this->productId,
            $this->sku,
            $this->name,
            (int) $this->unitsSold,
            new Money($this->revenue, new Currency($this->currency)),
        );
    }
}
