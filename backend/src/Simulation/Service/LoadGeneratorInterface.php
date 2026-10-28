<?php

declare(strict_types=1);

namespace App\Simulation\Service;

use App\Simulation\ReadModel\SimulationRun;

interface LoadGeneratorInterface
{
    /**
     * Lets the run's buyers shop through the public API until it ends, counting what happens
     * in the simulation store; returns when every buyer has finished their last order.
     */
    public function run(SimulationRun $run): void;
}
