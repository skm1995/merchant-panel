<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\Exception\OrderNotFoundException;
use App\Order\ReadModel\OrderView;
use App\Order\Repository\OrderReadRepositoryInterface;
use App\Order\Service\OrderAccess;

final class GetOrderHandler
{
    public function __construct(
        private readonly OrderReadRepositoryInterface $orders,
        private readonly OrderAccess $access,
    ) {
    }

    public function handle(GetOrderQuery $query): OrderView
    {
        $this->access->verify($query->orderId, $query->accessToken);

        return $this->orders->find($query->orderId) ?? throw new OrderNotFoundException();
    }
}
