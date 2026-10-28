<?php

declare(strict_types=1);

namespace App\Order\Service;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Message\OrderPaidMessage;
use App\Order\Repository\OrderRepositoryInterface;
use App\Shared\Messaging\Outbox\OutboxInterface;
use Psr\Clock\ClockInterface;

/**
 * Public Order service for other features that settle orders, such as payments.
 * Callers must run it inside their transaction.
 */
final readonly class OrderSettlement
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderStatusChange $statusChange,
        private OutboxInterface $outbox,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Marks a pending order paid, turns its reservation into sold stock, and announces the
     * payment through the outbox; returns false when the order can no longer be paid, for
     * example because it was cancelled.
     */
    public function markPaid(OrderId $id): bool
    {
        $order = $this->orders->findState($id) ?? throw new OrderNotFoundException();

        if (!$this->statusChange->change($order, OrderStatusEnum::PAID)) {
            return false;
        }

        $this->outbox->add(new OrderPaidMessage($id), $this->clock->now());

        return true;
    }
}
