<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use App\Simulation\ReadModel\SimulationProduct;

/** @implements EntityRecord<SimulationProduct> */
#[HydratedRecord]
final readonly class SimulationProductRecord implements EntityRecord
{
    public int $productId;
    public string $sku;
    public string $name;
    public int $stock;
    public int $reserved;

    public function toEntity(): SimulationProduct
    {
        return new SimulationProduct($this->productId, $this->sku, $this->name, $this->stock, $this->reserved);
    }
}
