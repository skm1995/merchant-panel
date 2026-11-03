<?php

declare(strict_types=1);

namespace App\Simulation\Query;

use App\Simulation\Exception\SimulationNotFoundException;
use App\Simulation\ReadModel\SimulationCounters;
use App\Simulation\ReadModel\SimulationView;
use App\Simulation\Repository\SimulationCatalogInterface;
use App\Simulation\Repository\SimulationStoreInterface;
use Psr\Clock\ClockInterface;

final readonly class GetCurrentSimulationHandler
{
    public function __construct(
        private SimulationStoreInterface $store,
        private SimulationCatalogInterface $catalog,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetCurrentSimulationQuery $query): SimulationView
    {
        $run = $this->store->findCurrent() ?? throw new SimulationNotFoundException();

        return new SimulationView(
            $run,
            $run->stateAt($this->clock->now()),
            SimulationCounters::fromCounts($this->store->counts($run->id)),
            $this->catalog->currentProducts(),
        );
    }
}
