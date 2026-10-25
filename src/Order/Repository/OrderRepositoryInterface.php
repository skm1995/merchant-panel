<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\Order;
use App\Order\Entity\OrderPlacement;

interface OrderRepositoryInterface
{
    /**
     * @throws \App\Order\Exception\IdempotencyKeyReusedException when another order holds the key
     */
    public function add(Order $order): void;

    public function findPlacement(string $idempotencyKey): ?OrderPlacement;
}
