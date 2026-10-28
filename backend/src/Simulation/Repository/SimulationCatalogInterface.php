<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Simulation\ReadModel\SimulationProduct;

/**
 * The products the simulated buyers shop for, with SKUs starting with "bf-", and the orders
 * a simulation placed, whose idempotency keys start with "simulation:<id>:".
 */
interface SimulationCatalogInterface
{
    /**
     * Creates the products when they are missing and gives each the available stock, on top
     * of what open orders still hold.
     *
     * @return list<SimulationProduct>
     */
    public function prepareProducts(int $count, int $availableStock): array;

    /** @return list<SimulationProduct> */
    public function currentProducts(): array;

    /**
     * Units in the simulation's paid orders.
     */
    public function paidUnits(string $simulationId): int;
}
