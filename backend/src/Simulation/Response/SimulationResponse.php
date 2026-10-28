<?php

declare(strict_types=1);

namespace App\Simulation\Response;

use App\Shared\Contract\Arrayable;
use App\Simulation\ReadModel\SimulationProduct;
use App\Simulation\ReadModel\SimulationView;

final readonly class SimulationResponse implements Arrayable
{
    private const string RUNNING = 'running';
    private const string FINISHED = 'finished';
    private const string DATE_FORMAT = DATE_ATOM;
    private const string ID_KEY = 'id';
    private const string STATE_KEY = 'state';
    private const string STARTED_AT_KEY = 'started_at';
    private const string ENDS_AT_KEY = 'ends_at';
    private const string BUYERS_KEY = 'buyers';
    private const string COUNTERS_KEY = 'counters';
    private const string ORDERS_PLACED_KEY = 'orders_placed';
    private const string ORDERS_PAID_KEY = 'orders_paid';
    private const string SOLD_OUT_KEY = 'sold_out';
    private const string FAILED_REQUESTS_KEY = 'failed_requests';
    private const string PRODUCTS_KEY = 'products';
    private const string PRODUCT_ID_KEY = 'product_id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string STOCK_AT_START_KEY = 'stock_at_start';
    private const string STOCK_KEY = 'stock';
    private const string RESERVED_KEY = 'reserved';
    private const string CHECK_KEY = 'check';
    private const string CONSISTENT_KEY = 'consistent';
    private const string UNITS_SOLD_KEY = 'units_sold';
    private const string UNITS_PAID_KEY = 'units_paid';

    public function __construct(private SimulationView $simulation)
    {
    }

    public function toArray(): array
    {
        $run = $this->simulation->run;
        $counters = $this->simulation->counters;

        return [
            self::ID_KEY => $run->id,
            self::STATE_KEY => $run->isFinished() ? self::FINISHED : self::RUNNING,
            self::STARTED_AT_KEY => $run->startedAt->format(self::DATE_FORMAT),
            self::ENDS_AT_KEY => $run->endsAt->format(self::DATE_FORMAT),
            self::BUYERS_KEY => $run->buyers,
            self::COUNTERS_KEY => [
                self::ORDERS_PLACED_KEY => $counters->ordersPlaced,
                self::ORDERS_PAID_KEY => $counters->ordersPaid,
                self::SOLD_OUT_KEY => $counters->soldOut,
                self::FAILED_REQUESTS_KEY => $counters->failedRequests,
            ],
            self::PRODUCTS_KEY => array_map(fn (SimulationProduct $product): array => [
                self::PRODUCT_ID_KEY => $product->productId,
                self::SKU_KEY => $product->sku,
                self::NAME_KEY => $product->name,
                self::STOCK_AT_START_KEY => $this->simulation->stockAtStart($product->productId),
                self::STOCK_KEY => $product->stock,
                self::RESERVED_KEY => $product->reserved,
            ], $this->simulation->products),
            self::CHECK_KEY => $run->check === null ? null : [
                self::CONSISTENT_KEY => $run->check->isConsistent(),
                self::UNITS_SOLD_KEY => $run->check->unitsSold,
                self::UNITS_PAID_KEY => $run->check->unitsPaid,
            ],
        ];
    }
}
