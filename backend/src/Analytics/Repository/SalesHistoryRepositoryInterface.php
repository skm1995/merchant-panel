<?php

declare(strict_types=1);

namespace App\Analytics\Repository;

use App\Analytics\ValueObject\HistoryOrder;
use App\Analytics\ValueObject\HistoryProduct;

/**
 * Generated sales history: products whose SKU starts with "history-" and orders whose
 * idempotency key starts with "history:", each with its own checked-out cart.
 */
interface SalesHistoryRepositoryInterface
{
    public function hasHistory(): bool;

    /**
     * @param list<HistoryProduct> $products
     * @return list<int> the ids the products were stored under, in the same order
     */
    public function addProducts(array $products): array;

    /**
     * Stores the orders with their carts and lines; callers keep a batch to about a thousand
     * orders, so one statement per table stays within MySQL's placeholder limit.
     *
     * @param non-empty-list<HistoryOrder> $orders
     */
    public function addOrders(array $orders): void;

    /**
     * Deletes up to $limit history orders with their lines and carts and returns how many.
     */
    public function removeBatch(int $limit): int;

    /**
     * Deletes history products that no order or cart refers to any more.
     */
    public function removeUnusedProducts(): int;

    /**
     * Rebuilds the tables history was removed from, so MySQL gives the freed disk space back.
     */
    public function reclaimSpace(): void;
}
