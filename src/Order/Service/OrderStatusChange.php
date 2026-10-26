<?php

declare(strict_types=1);

namespace App\Order\Service;

use App\Catalog\Service\StockReservation;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Repository\OrderRepositoryInterface;
use LogicException;

/**
 * Moves an order to a new status with optimistic locking and applies the stock effect of
 * that status: payment turns the reservation into sold stock, cancellation and expiry give
 * it back. Callers must run it inside a transaction.
 */
final readonly class OrderStatusChange
{
    private const string NO_STOCK_EFFECT_FORMAT = 'Changing an order to %s has no defined stock effect.';

    public function __construct(
        private OrderRepositoryInterface $orders,
        private StockReservation $stock,
    ) {
    }

    /**
     * Returns false when the current status does not allow the change or the order changed
     * since it was read.
     */
    public function change(OrderState $order, OrderStatusEnum $to): bool
    {
        if (
            !$order->status->canTransitionTo($to)
            || !$this->orders->transition($order->id, $order->status, $to, $order->version)
        ) {
            return false;
        }

        match ($to) {
            OrderStatusEnum::PAID => $this->stock->commit($order->quantities),
            OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED => $this->stock->release($order->quantities),
            OrderStatusEnum::PENDING_PAYMENT => throw new LogicException(sprintf(self::NO_STOCK_EFFECT_FORMAT, $to->value)),
        };

        return true;
    }
}
