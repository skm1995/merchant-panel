<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

#[HydratedRecord]
final readonly class ProductRecord
{
    public int $id;
    public string $sku;
    public string $name;
    public int $priceAmount;
    /** @var non-empty-string */
    public string $priceCurrency;
    public int $stock;
    public int $reserved;
    public int $version;

    public function toProduct(): Product
    {
        return new Product(
            $this->id,
            $this->sku,
            $this->name,
            new Money($this->priceAmount, new Currency($this->priceCurrency)),
            $this->stock,
            $this->reserved,
            $this->version,
        );
    }
}
