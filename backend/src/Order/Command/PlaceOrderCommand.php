<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Cart\Entity\CartId;

final readonly class PlaceOrderCommand
{
    public function __construct(public CartId $cartId, public string $idempotencyKey)
    {
    }
}
