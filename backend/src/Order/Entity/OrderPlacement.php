<?php

declare(strict_types=1);

namespace App\Order\Entity;

use App\Cart\Entity\CartId;

/**
 * Which cart an idempotency key already placed an order for.
 */
final readonly class OrderPlacement
{
    public function __construct(public OrderId $orderId, public CartId $cartId)
    {
    }
}
