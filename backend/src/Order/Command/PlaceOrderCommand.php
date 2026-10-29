<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Cart\Entity\CartId;
use App\Shared\Messaging\Bus\CommandInterface;

/** @implements CommandInterface<PlaceOrderResult> */
final readonly class PlaceOrderCommand implements CommandInterface
{
    public function __construct(public CartId $cartId, public string $idempotencyKey)
    {
    }
}
