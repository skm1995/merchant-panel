<?php

declare(strict_types=1);

namespace Tests\Unit\Simulation\Command;

use App\Simulation\Command\RunSimulationCommand;
use App\Simulation\Command\RunSimulationHandler;
use App\Simulation\ReadModel\SimulationCheck;
use App\Simulation\ReadModel\SimulationProduct;
use App\Simulation\ReadModel\SimulationRun;
use App\Simulation\Repository\SimulationCatalogInterface;
use App\Simulation\Service\LoadGeneratorInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunSimulationHandlerTest extends TestCase
{
    private const string ID = 'abc123';

    public function testChecksUnitsTakenFromStockAgainstPaidUnits(): void
    {
        $store = new InMemorySimulationStore(self::simulationRun());

        $this->handler($store, paidUnits: 7)->handle(new RunSimulationCommand(self::ID));

        self::assertEquals(new SimulationCheck(7, 7), $store->current?->check, 'TV 10 → 6 and phone 5 → 2: 4 + 3 units sold.');
        self::assertTrue($store->current?->check?->isConsistent());
    }

    public function testFlagsUnitsSoldWithoutAPaidOrder(): void
    {
        $store = new InMemorySimulationStore(self::simulationRun());

        $this->handler($store, paidUnits: 6)->handle(new RunSimulationCommand(self::ID));

        self::assertFalse($store->current?->check?->isConsistent());
    }

    public function testFinishesTheRunEvenWhenTheBuyersFail(): void
    {
        $store = new InMemorySimulationStore(self::simulationRun());
        $load = $this->createStub(LoadGeneratorInterface::class);
        $load->method('run')->willThrowException(new RuntimeException('Proxy unreachable.'));

        try {
            $this->handler($store, paidUnits: 7, load: $load)->handle(new RunSimulationCommand(self::ID));
            self::fail('The failure must reach Messenger, which logs it.');
        } catch (RuntimeException) {
            self::assertTrue($store->current?->isFinished() ?? false, 'A failed run must not stay "running" forever.');
        }
    }

    public function testLeavesAFinishedOrAnotherRunAlone(): void
    {
        $finished = self::simulationRun()->finish(new SimulationCheck(1, 1));
        $store = new InMemorySimulationStore($finished);
        $load = $this->createMock(LoadGeneratorInterface::class);
        $load->expects(self::never())->method('run');

        $this->handler($store, paidUnits: 7, load: $load)->handle(new RunSimulationCommand(self::ID));
        $this->handler($store, paidUnits: 7, load: $load)->handle(new RunSimulationCommand('another'));

        self::assertSame($finished, $store->current);
    }

    private function handler(InMemorySimulationStore $store, int $paidUnits, ?LoadGeneratorInterface $load = null): RunSimulationHandler
    {
        $catalog = $this->createStub(SimulationCatalogInterface::class);
        $catalog->method('currentProducts')->willReturn([
            new SimulationProduct(1, 'bf-1', 'TV', 6, 0),
            new SimulationProduct(2, 'bf-2', 'Phone', 2, 0),
        ]);
        $catalog->method('paidUnits')->willReturn($paidUnits);

        return new RunSimulationHandler($store, $catalog, $load ?? $this->createStub(LoadGeneratorInterface::class));
    }

    private static function simulationRun(): SimulationRun
    {
        return new SimulationRun(self::ID, new DateTimeImmutable('2026-11-27 10:00'), new DateTimeImmutable('2026-11-27 10:01'), 50, [
            new SimulationProduct(1, 'bf-1', 'TV', 10, 0),
            new SimulationProduct(2, 'bf-2', 'Phone', 5, 0),
        ]);
    }
}
