<?php

declare(strict_types=1);

namespace App\Analytics\Repository;

use App\Order\Entity\OrderId;
use DateTimeImmutable;

/**
 * The daily_product_sales summary: units and revenue of paid orders per day and product.
 */
interface DailySalesRepositoryInterface
{
    /**
     * Recomputes the summary of one day from its paid orders, replacing what was stored, so
     * refreshing a day twice gives the same result. Callers run it inside a transaction.
     */
    public function refreshDay(DateTimeImmutable $day): void;

    /**
     * The day the order was placed on, or null when the order does not exist.
     */
    public function findPlacementDay(OrderId $orderId): ?DateTimeImmutable;
}
