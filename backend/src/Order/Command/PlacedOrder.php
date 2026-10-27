<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Order\Entity\OrderId;

/**
 * The order a checkout placed, or found again for a repeated request, with the token the
 * guest needs to see, cancel, or pay for it.
 */
final readonly class PlacedOrder
{
    public function __construct(public OrderId $orderId, public string $accessToken)
    {
    }
}
