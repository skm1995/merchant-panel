<?php

declare(strict_types=1);

namespace Tests\Unit\Simulation\Command;

use App\Simulation\ReadModel\SimulationRun;
use App\Simulation\Repository\SimulationStoreInterface;

final class InMemorySimulationStore implements SimulationStoreInterface
{
    public function __construct(public ?SimulationRun $current)
    {
    }

    public bool $slotFree = true;

    public function reserveSlot(string $simulationId, int $seconds): bool
    {
        return $this->slotFree;
    }

    public function secondsUntilFree(): int
    {
        return 0;
    }

    public function save(SimulationRun $run): void
    {
        $this->current = $run;
    }

    public function findCurrent(): ?SimulationRun
    {
        return $this->current;
    }

    public function increment(string $simulationId, string $counter): void
    {
    }

    public function counts(string $simulationId): array
    {
        return [];
    }
}
