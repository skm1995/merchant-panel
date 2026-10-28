<?php

declare(strict_types=1);

namespace App\Simulation\ReadModel;

final readonly class SimulationProduct
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $stock,
        public int $reserved,
    ) {
    }
}
