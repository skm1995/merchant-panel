<?php

declare(strict_types=1);

namespace Tests\Functional\Simulation;

use App\Shared\DependencyInjection\ContainerFactory;
use App\Simulation\Infrastructure\HttpLoadGenerator;
use App\Simulation\ReadModel\SimulationCounters;
use App\Simulation\ReadModel\SimulationRun;
use App\Simulation\Repository\SimulationCatalogInterface;
use App\Simulation\Repository\SimulationStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The simulator's buyers against the real test stack, run in this process: they create
 * carts, check out, pay, and send signed webhooks through the proxy until the run ends.
 */
final class HttpLoadGeneratorTest extends TestCase
{
    private const int BUYERS = 3;
    private const int STOCK_PER_PRODUCT = 2;
    private const int PRODUCTS = 5;
    private const string RUN_FOR = '+2 seconds';

    public function testBuyersPayForEveryUnitTheyTakeFromTheShelves(): void
    {
        $container = ContainerFactory::create();
        $catalog = $container->get(SimulationCatalogInterface::class);
        $store = $container->get(SimulationStoreInterface::class);
        $products = $catalog->prepareProducts(self::PRODUCTS, self::STOCK_PER_PRODUCT);
        $run = new SimulationRun(uniqid('load-test-', true), new DateTimeImmutable(), new DateTimeImmutable(self::RUN_FOR), self::BUYERS, $products);

        $container->get(HttpLoadGenerator::class)->run($run);

        $counts = SimulationCounters::fromCounts($store->counts($run->id));
        $unitsTaken = 0;
        foreach ($catalog->currentProducts() as $product) {
            $unitsTaken += self::STOCK_PER_PRODUCT - ($product->stock - $product->reserved);
        }
        self::assertGreaterThan(0, $counts->ordersPaid);
        self::assertSame($counts->ordersPlaced, $counts->ordersPaid, 'Every buyer who checked out also paid.');
        self::assertSame(0, $counts->failedRequests);
        self::assertSame($unitsTaken, $catalog->paidUnits($run->id), 'Every unit taken from the shelves was paid for once, however fast the buyers were.');
    }
}
