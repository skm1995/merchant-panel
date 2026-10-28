<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Order\Entity\OrderId;

/**
 * What PlaceOrderHandler returns: the order a checkout placed, or found again for a repeated
 * request, with the token the guest needs to see, cancel, or pay for it. A data transfer
 * object from the handler to the controller, not a command.
 */
final readonly class PlaceOrderResult
{
    public function __construct(public OrderId $orderId, public string $accessToken)
    {
    }
}
