<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Catalog\Message\ProductStockChangedMessage;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\DependencyInjection\Env;
use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Simulation\Exception\SimulationUnavailableException;
use App\Simulation\ReadModel\SimulationProduct;
use App\Simulation\ReadModel\SimulationRun;
use App\Simulation\Repository\SimulationCatalogInterface;
use App\Simulation\Repository\SimulationStoreInterface;
use Psr\Clock\ClockInterface;

/**
 * Starts a Black Friday simulation: takes the one slot for its run and cool-down, resets the
 * stock of its products, and records the run for the simulator through the outbox, in the
 * same transaction as the new stock.
 */
final readonly class StartSimulationHandler
{
    private const int ID_BYTES = 8;
    private const string SECONDS_MODIFIER_FORMAT = '+%d seconds';

    public function __construct(
        private SimulationStoreInterface $store,
        private SimulationCatalogInterface $catalog,
        private OutboxInterface $outbox,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
        #[Env('SIMULATION_SECONDS')] private int $seconds,
        #[Env('SIMULATION_COOLDOWN_SECONDS')] private int $cooldownSeconds,
        #[Env('SIMULATION_BUYERS')] private int $buyers,
        #[Env('SIMULATION_PRODUCTS')] private int $products,
        #[Env('SIMULATION_STOCK')] private int $stock,
    ) {
    }

    public function handle(StartSimulationCommand $command): string
    {
        $id = bin2hex(random_bytes(self::ID_BYTES));

        if (!$this->store->reserveSlot($id, $this->seconds + $this->cooldownSeconds)) {
            throw new SimulationUnavailableException($this->store->secondsUntilFree());
        }

        $now = $this->clock->now();
        $products = $this->transactions->run(function () use ($id, $now): array {
            $products = $this->catalog->prepareProducts($this->products, $this->stock);
            $this->outbox->add(
                new ProductStockChangedMessage(array_map(static fn (SimulationProduct $product): int => $product->productId, $products)),
                $now,
            );
            $this->outbox->add(new RunSimulationCommand($id), $now);

            return $products;
        });

        $this->store->save(new SimulationRun($id, $now, $now->modify(sprintf(self::SECONDS_MODIFIER_FORMAT, $this->seconds)), $this->buyers, $products));

        return $id;
    }
}
