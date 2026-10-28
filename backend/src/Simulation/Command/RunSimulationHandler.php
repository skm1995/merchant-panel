<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Shared\Messaging\AsMessageHandler;
use App\Simulation\ReadModel\SimulationCheck;
use App\Simulation\ReadModel\SimulationRun;
use App\Simulation\Repository\SimulationCatalogInterface;
use App\Simulation\Repository\SimulationStoreInterface;
use App\Simulation\Service\LoadGeneratorInterface;

/**
 * Runs a started simulation in the simulator, then checks it against the database: units
 * taken from the products' stock must equal units in the simulation's paid orders.
 *
 * Idempotent, because the message may be delivered twice: a run that is not the current
 * one, or that already has its check, is left alone.
 */
final readonly class RunSimulationHandler
{
    public function __construct(
        private SimulationStoreInterface $store,
        private SimulationCatalogInterface $catalog,
        private LoadGeneratorInterface $load,
    ) {
    }

    #[AsMessageHandler]
    public function handle(RunSimulationCommand $command): void
    {
        $run = $this->store->findCurrent();

        if ($run === null || $run->id !== $command->simulationId || $run->isFinished()) {
            return;
        }

        try {
            $this->load->run($run);
        } finally {
            // Even a failed run gets its check, so it never stays "running", and a repeated
            // delivery of the message finds it finished.
            $this->store->save($run->finish($this->check($run)));
        }
    }

    private function check(SimulationRun $run): SimulationCheck
    {
        $unitsSold = 0;

        foreach ($this->catalog->currentProducts() as $product) {
            foreach ($run->productsAtStart as $atStart) {
                if ($atStart->productId === $product->productId) {
                    $unitsSold += $atStart->stock - $product->stock;
                }
            }
        }

        return new SimulationCheck($unitsSold, $this->catalog->paidUnits($run->id));
    }
}
