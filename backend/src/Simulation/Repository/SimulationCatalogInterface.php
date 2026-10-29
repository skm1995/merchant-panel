<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Simulation\ReadModel\SimulationProduct;
use DateTimeImmutable;

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

    /**
     * The days on which the paid orders of all simulations so far were placed.
     *
     * @return list<DateTimeImmutable>
     */
    public function paidOrderDays(): array;

    /**
     * Removes the orders of past simulations that no longer wait for payment, with their
     * payments, and every cart of simulation products that has no order left, including the
     * carts of buyers who found the shelves empty. Orders waiting for payment keep their
     * reserved stock until they expire, so a later run removes them.
     */
    public function removeFinishedOrders(): void;
}
