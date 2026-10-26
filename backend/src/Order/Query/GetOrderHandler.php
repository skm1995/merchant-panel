<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\Exception\OrderNotFoundException;
use App\Order\ReadModel\OrderView;
use App\Order\Repository\OrderReadRepositoryInterface;

final class GetOrderHandler
{
    public function __construct(private readonly OrderReadRepositoryInterface $orders)
    {
    }

    public function handle(GetOrderQuery $query): OrderView
    {
        return $this->orders->find($query->orderId) ?? throw new OrderNotFoundException();
    }
}
