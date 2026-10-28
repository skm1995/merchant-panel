<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics\Service;

use App\Analytics\Repository\SalesHistoryRepositoryInterface;

final class RecordingSalesHistoryRepository implements SalesHistoryRepositoryInterface
{
    private const int FIRST_PRODUCT_ID = 501;

    public bool $exists = false;
    public int $products = 0;
    /** @var list<list<string>> idempotency keys of each stored batch */
    public array $orderBatches = [];
    /** @var list<int> */
    public array $batches = [];
    public int $removedProducts = 0;
    public bool $reclaimed = false;

    public function hasHistory(): bool
    {
        return $this->exists;
    }

    public function addProducts(array $products): array
    {
        $this->products = count($products);

        return range(self::FIRST_PRODUCT_ID, self::FIRST_PRODUCT_ID + count($products) - 1);
    }

    public function addOrders(array $orders): void
    {
        $this->orderBatches[] = array_map(static fn ($order): string => $order->idempotencyKey, $orders);
    }

    public function removeBatch(int $limit): int
    {
        return array_shift($this->batches) ?? 0;
    }

    public function removeUnusedProducts(): int
    {
        return $this->removedProducts;
    }

    public function reclaimSpace(): void
    {
        $this->reclaimed = true;
    }
}
