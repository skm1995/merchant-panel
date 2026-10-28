<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\ProductPace;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;

/** @implements EntityRecord<ProductPace> */
#[HydratedRecord]
final readonly class ProductPaceRecord implements EntityRecord
{
    public int $productId;
    public string $sku;
    public string $name;
    public int $available;
    /** @var numeric-string */
    public string $unitsSold;

    public function toEntity(): ProductPace
    {
        return new ProductPace($this->productId, $this->sku, $this->name, $this->available, (int) $this->unitsSold);
    }
}
