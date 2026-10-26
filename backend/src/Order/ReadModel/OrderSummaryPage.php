<?php

declare(strict_types=1);

namespace App\Order\ReadModel;

use App\Order\Entity\OrderId;

final readonly class OrderSummaryPage
{
    /** @param list<OrderSummary> $orders */
    public function __construct(public array $orders, public ?OrderId $nextCursor)
    {
    }
}
