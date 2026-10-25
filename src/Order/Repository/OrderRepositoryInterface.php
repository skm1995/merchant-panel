<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\Order;
use App\Order\Entity\OrderId;
use App\Order\Entity\OrderPlacement;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;

interface OrderRepositoryInterface
{
    /**
     * @throws \App\Order\Exception\IdempotencyKeyReusedException when another order holds the key
     */
    public function add(Order $order): void;

    public function findPlacement(string $idempotencyKey): ?OrderPlacement;

    public function findState(OrderId $id): ?OrderState;

    /**
     * Changes the status only while the order still has the expected status and version,
     * incrementing the version; returns false when someone changed the order meanwhile.
     */
    public function transition(OrderId $id, OrderStatusEnum $from, OrderStatusEnum $to, int $expectedVersion): bool;
}
