<?php

declare(strict_types=1);

namespace Tests\Unit\Simulation\Command;

use App\Catalog\Message\ProductStockChangedMessage;
use App\Simulation\Command\RunSimulationCommand;
use App\Simulation\Command\StartSimulationCommand;
use App\Simulation\Command\StartSimulationHandler;
use App\Simulation\Exception\SimulationUnavailableException;
use App\Simulation\ReadModel\SimulationProduct;
use App\Simulation\Repository\SimulationCatalogInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Support\RecordingOutbox;

final class StartSimulationHandlerTest extends TestCase
{
    private const string NOW = '2026-11-27 10:00:00';

    public function testResetsTheStockAndHandsTheRunToTheSimulator(): void
    {
        $store = new InMemorySimulationStore(null);
        $outbox = new RecordingOutbox();
        $catalog = $this->createMock(SimulationCatalogInterface::class);
        $catalog->expects(self::once())->method('prepareProducts')->with(5, 400)->willReturn([new SimulationProduct(7, 'bf-1', 'TV', 400, 0)]);

        $id = $this->handler($store, $catalog, $outbox)->handle(new StartSimulationCommand());

        self::assertSame($id, $store->current?->id);
        self::assertSame('2026-11-27 10:01:00', $store->current->endsAt->format('Y-m-d H:i:s'));
        self::assertEquals([new ProductStockChangedMessage([7]), new RunSimulationCommand($id)], $outbox->messages(), 'The cache forgets the old stock, and the simulator gets the run.');
    }

    public function testRefusesWhileAnotherSimulationHoldsTheSlot(): void
    {
        $store = new InMemorySimulationStore(null);
        $store->slotFree = false;
        $outbox = new RecordingOutbox();
        $catalog = $this->createMock(SimulationCatalogInterface::class);
        $catalog->expects(self::never())->method('prepareProducts');

        try {
            $this->handler($store, $catalog, $outbox)->handle(new StartSimulationCommand());
            self::fail('A second simulation must wait.');
        } catch (SimulationUnavailableException) {
            self::assertNull($store->current);
            self::assertSame([], $outbox->messages());
        }
    }

    private function handler(InMemorySimulationStore $store, SimulationCatalogInterface $catalog, RecordingOutbox $outbox): StartSimulationHandler
    {
        return new StartSimulationHandler($store, $catalog, $outbox, new ImmediateTransactionRunner(), new FrozenClock(self::NOW), 60, 300, 50, 5, 400);
    }
}
