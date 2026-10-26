<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Order\Entity\OrderId;

final readonly class CancelOrderCommand
{
    public function __construct(public OrderId $orderId, public int $expectedVersion)
    {
    }
}
