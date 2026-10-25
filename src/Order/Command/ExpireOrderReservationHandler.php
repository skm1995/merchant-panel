<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Catalog\Service\StockReservation;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Repository\OrderRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;

/**
 * Expires an unpaid order when its reservation ends and gives its stock back.
 *
 * Idempotent, because the message may be delivered more than once: only an order still
 * awaiting payment changes, through a conditional update on its version, so a payment or
 * cancellation that won the race is never undone.
 */
final class ExpireOrderReservationHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly StockReservation $stock,
        private readonly TransactionRunnerInterface $transactions,
    ) {
    }

    public function handle(ExpireOrderReservationCommand $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $order = $this->orders->findState($command->orderId);

            if ($order === null || !$order->status->canTransitionTo(OrderStatusEnum::EXPIRED)) {
                return;
            }

            if ($this->orders->transition($order->id, $order->status, OrderStatusEnum::EXPIRED, $order->version)) {
                $this->stock->release($order->quantities);
            }
        }, IsolationLevelEnum::READ_COMMITTED);
    }
}
