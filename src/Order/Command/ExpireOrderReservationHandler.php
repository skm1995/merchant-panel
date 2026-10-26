<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Order\Enum\OrderStatusEnum;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderStatusChange;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\Messaging\AsMessageHandler;

/**
 * Expires an unpaid order when its reservation ends and gives its stock back.
 *
 * Idempotent, because the message may be delivered more than once: only an order still
 * awaiting payment changes, through a conditional update on its version, so a payment or
 * cancellation that won the race is never undone.
 */
final readonly class ExpireOrderReservationHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderStatusChange $statusChange,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function handle(ExpireOrderReservationCommand $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $order = $this->orders->findState($command->orderId);

            if ($order !== null) {
                $this->statusChange->change($order, OrderStatusEnum::EXPIRED);
            }
        }, IsolationLevelEnum::READ_COMMITTED);
    }
}
