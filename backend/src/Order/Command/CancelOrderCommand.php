<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Shared\Messaging\Bus\CommandInterface;
use App\Order\Entity\OrderId;

/** @implements CommandInterface<null> */
final readonly class CancelOrderCommand implements CommandInterface
{
    public function __construct(public OrderId $orderId, public string $accessToken, public int $expectedVersion)
    {
    }
}
