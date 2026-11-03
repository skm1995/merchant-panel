<?php

declare(strict_types=1);

namespace App\Simulation\ReadModel;

use App\Simulation\Enum\SimulationStateEnum;

/**
 * A simulation as the panel shows it: the stored run and its state, its live counters, and the
 * products' current stock next to the stock they started with.
 */
final readonly class SimulationView
{
    /** @param list<SimulationProduct> $products the products' current stock */
    public function __construct(
        public SimulationRun $run,
        public SimulationStateEnum $state,
        public SimulationCounters $counters,
        public array $products,
    ) {
    }

    public function stockAtStart(int $productId): int
    {
        foreach ($this->run->productsAtStart as $product) {
            if ($product->productId === $productId) {
                return $product->stock;
            }
        }

        return 0;
    }
}
