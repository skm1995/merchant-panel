<?php

declare(strict_types=1);

namespace App\Order\Service;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Repository\OrderRepositoryInterface;

/**
 * Public Order service for other features that settle orders, such as payments.
 * Callers must run it inside their transaction.
 */
final class OrderSettlement
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderStatusChange $statusChange,
    ) {
    }

    /**
     * Marks a pending order paid and turns its reservation into sold stock; returns false
     * when the order can no longer be paid, for example because it was cancelled.
     */
    public function markPaid(OrderId $id): bool
    {
        $order = $this->orders->findState($id) ?? throw new OrderNotFoundException();

        return $this->statusChange->change($order, OrderStatusEnum::PAID);
    }
}
