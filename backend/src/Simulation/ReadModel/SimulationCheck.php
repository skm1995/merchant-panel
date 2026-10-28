<?php

declare(strict_types=1);

namespace App\Simulation\ReadModel;

/**
 * The end of a simulation compared with its orders: units taken from stock against units in
 * paid orders. They match when no unit was sold twice and none was lost.
 */
final readonly class SimulationCheck
{
    public function __construct(public int $unitsSold, public int $unitsPaid)
    {
    }

    public function isConsistent(): bool
    {
        return $this->unitsSold === $this->unitsPaid;
    }
}
