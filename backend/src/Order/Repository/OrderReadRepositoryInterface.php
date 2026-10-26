<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\OrderId;
use App\Order\ReadModel\OrderView;

interface OrderReadRepositoryInterface
{
    public function find(OrderId $id): ?OrderView;
}
