<?php

declare(strict_types=1);

namespace App\Order\Query;

use App\Order\ReadModel\OrderSummaryPage;
use App\Shared\Messaging\Bus\QueryInterface;
use App\Order\Entity\OrderId;

/**
 * A page of orders, newest first, after the order the previous page ended with.
 *
 * @implements QueryInterface<OrderSummaryPage>
 */
final readonly class ListOrdersQuery implements QueryInterface
{
    public function __construct(public int $limit, public ?OrderId $after)
    {
    }
}
