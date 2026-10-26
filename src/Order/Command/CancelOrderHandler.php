<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\InvalidOrderTransitionException;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Exception\OrderVersionMismatchException;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderStatusChange;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;

/**
 * Cancels a pending order with optimistic locking: the caller sends the version it read,
 * and the conditional status update fails when anyone changed the order in between.
 */
final readonly class CancelOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderStatusChange $statusChange,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function handle(CancelOrderCommand $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $order = $this->orders->findState($command->orderId) ?? throw new OrderNotFoundException();

            if ($order->version !== $command->expectedVersion) {
                throw new OrderVersionMismatchException();
            }

            if (!$order->status->canTransitionTo(OrderStatusEnum::CANCELLED)) {
                throw new InvalidOrderTransitionException($order->status, OrderStatusEnum::CANCELLED);
            }

            if (!$this->statusChange->change($order, OrderStatusEnum::CANCELLED)) {
                throw new OrderVersionMismatchException();
            }
        }, IsolationLevelEnum::READ_COMMITTED);
    }
}
