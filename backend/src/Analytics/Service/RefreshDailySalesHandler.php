<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use App\Analytics\Repository\DailySalesRepositoryInterface;
use App\Order\Message\OrderPaidMessage;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\Messaging\AsMessageHandler;
use App\Simulation\Message\SimulationOrdersRemovedMessage;

/**
 * Keeps the daily sales summary current: when an order is paid, the worker recomputes the
 * day it was placed on, and when a simulation removes paid orders, the days they were on.
 *
 * Idempotent, because the message may be delivered more than once: the day is recomputed
 * from the orders and replaced, never added to. READ COMMITTED keeps the INSERT ... SELECT
 * from locking the orders it reads, so checkouts never wait for the summary.
 */
final readonly class RefreshDailySalesHandler
{
    public function __construct(
        private DailySalesRepositoryInterface $sales,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function handle(OrderPaidMessage $message): void
    {
        $day = $this->sales->findPlacementDay($message->orderId);

        if ($day !== null) {
            $this->transactions->run(fn () => $this->sales->refreshDay($day), IsolationLevelEnum::READ_COMMITTED);
        }
    }

    #[AsMessageHandler]
    public function handleRemovedSimulationOrders(SimulationOrdersRemovedMessage $message): void
    {
        foreach ($message->days as $day) {
            $this->transactions->run(fn () => $this->sales->refreshDay($day), IsolationLevelEnum::READ_COMMITTED);
        }
    }
}
