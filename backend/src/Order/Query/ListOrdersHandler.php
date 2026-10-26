<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\ReadModel\OrderSummaryPage;
use App\Order\Repository\OrderReadRepositoryInterface;

final readonly class ListOrdersHandler
{
    public function __construct(private OrderReadRepositoryInterface $orders)
    {
    }

    public function handle(ListOrdersQuery $query): OrderSummaryPage
    {
        $orders = $this->orders->findPage($query->limit + 1, $query->after);
        $page = array_slice($orders, 0, $query->limit);
        $last = $page[count($page) - 1] ?? null;

        return new OrderSummaryPage($page, count($orders) > $query->limit ? $last?->id : null);
    }
}
