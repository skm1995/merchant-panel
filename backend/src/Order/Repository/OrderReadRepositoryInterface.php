<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\OrderId;
use App\Order\ReadModel\OrderSummary;
use App\Order\ReadModel\OrderView;

interface OrderReadRepositoryInterface
{
    public function find(OrderId $id): ?OrderView;

    /**
     * Orders newest first, starting after the given order; an unknown order gives an empty page.
     *
     * @return list<OrderSummary>
     */
    public function findPage(int $limit, ?OrderId $after): array;
}
