<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\Entity\OrderId;

/**
 * A page of orders, newest first, after the order the previous page ended with.
 */
final readonly class ListOrdersQuery
{
    public function __construct(public int $limit, public ?OrderId $after)
    {
    }
}
