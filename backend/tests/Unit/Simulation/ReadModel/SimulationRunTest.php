<?php

declare(strict_types=1);

namespace Tests\Unit\Simulation\ReadModel;

use App\Simulation\Enum\SimulationStateEnum;
use App\Simulation\ReadModel\SimulationCheck;
use App\Simulation\ReadModel\SimulationRun;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SimulationRunTest extends TestCase
{
    public function testIsRunningUntilItsEndAndWhileTheLastOrdersEnd(): void
    {
        $run = self::simulationRun();

        self::assertSame(SimulationStateEnum::RUNNING, $run->stateAt(new DateTimeImmutable('2026-11-27 09:59')));
        self::assertSame(SimulationStateEnum::RUNNING, $run->stateAt(new DateTimeImmutable('2026-11-27 10:01:30')));
    }

    public function testIsInterruptedWhenItsEndPassedWithoutACheck(): void
    {
        $run = self::simulationRun();

        self::assertSame(SimulationStateEnum::INTERRUPTED, $run->stateAt(new DateTimeImmutable('2026-11-27 10:10')));
    }

    public function testIsFinishedOnceItHasACheckHoweverLongAgoItEnded(): void
    {
        $run = self::simulationRun()->finish(new SimulationCheck(7, 7));

        self::assertSame(SimulationStateEnum::FINISHED, $run->stateAt(new DateTimeImmutable('2026-11-27 10:01:10')));
        self::assertSame(SimulationStateEnum::FINISHED, $run->stateAt(new DateTimeImmutable('2026-12-27 10:10')));
    }

    private static function simulationRun(): SimulationRun
    {
        return new SimulationRun('abc123', new DateTimeImmutable('2026-11-27 10:00'), new DateTimeImmutable('2026-11-27 10:01'), 50, []);
    }
}
