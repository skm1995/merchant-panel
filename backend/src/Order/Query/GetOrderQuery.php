<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\Entity\OrderId;
use App\Order\ReadModel\OrderView;
use App\Shared\Messaging\Bus\QueryInterface;

/** @implements QueryInterface<OrderView> */
final readonly class GetOrderQuery implements QueryInterface
{
    public function __construct(public OrderId $orderId, public string $accessToken)
    {
    }
}
