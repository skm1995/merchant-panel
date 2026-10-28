<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Simulation\ReadModel\SimulationRun;

/**
 * Where the running and the last simulation live, with their counters: shared by the web
 * server, which starts and shows simulations, and the simulator, which runs them.
 */
interface SimulationStoreInterface
{
    /**
     * Takes the one slot for a simulation for the given seconds, its run plus the cool-down;
     * returns false while another simulation holds it.
     */
    public function reserveSlot(string $simulationId, int $seconds): bool;

    public function secondsUntilFree(): int;

    public function save(SimulationRun $run): void;

    public function findCurrent(): ?SimulationRun;

    public function increment(string $simulationId, string $counter): void;

    /** @return array<string, int> */
    public function counts(string $simulationId): array;
}
