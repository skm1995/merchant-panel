<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Shared\Messaging\Bus\CommandInterface;
use App\Cart\Entity\CartId;

/** @implements CommandInterface<PlacedOrder> */
final readonly class PlaceOrderCommand implements CommandInterface
{
    public function __construct(public CartId $cartId, public string $idempotencyKey)
    {
    }
}
