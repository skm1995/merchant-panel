<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\TopProduct;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<TopProduct> */
#[HydratedRecord]
final readonly class TopProductRecord implements EntityRecord
{
    public int $revenueRank;
    public int $productId;
    public string $sku;
    public string $name;
    /** @var numeric-string */
    public string $unitsSold;
    /** @var numeric-string */
    public string $revenue;
    /** @var non-empty-string */
    public string $currency;

    public function toEntity(): TopProduct
    {
        return new TopProduct(
            $this->revenueRank,
            $this->productId,
            $this->sku,
            $this->name,
            (int) $this->unitsSold,
            new Money($this->revenue, new Currency($this->currency)),
        );
    }
}
