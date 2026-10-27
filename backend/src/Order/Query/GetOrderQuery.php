<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\Entity\OrderId;

final readonly class GetOrderQuery
{
    public function __construct(public OrderId $orderId, public string $accessToken)
    {
    }
}
