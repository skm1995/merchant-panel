<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Exception\InsufficientStockException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Shared\Messaging\Outbox\OutboxInterface;
use LogicException;
use Psr\Clock\ClockInterface;

/**
 * Reserves stock for several products at once. Callers must run it inside a transaction:
 * product rows are locked in ascending id order, so concurrent reservations of
 * overlapping products wait for each other instead of deadlocking. Every change is recorded
 * in the outbox in the same transaction, so cached product data is invalidated after commit.
 */
final readonly class StockReservation
{
    private const string RESERVATION_INCONSISTENCY_FORMAT = 'Product %d has fewer reserved units than are being settled.';

    public function __construct(
        private ProductRepositoryInterface $products,
        private OutboxInterface $outbox,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<int, int> $quantities quantities keyed by product id
     * @return list<Product> the locked products, as they were before the reservation
     */
    public function reserve(array $quantities): array
    {
        ksort($quantities);
        $products = [];

        foreach ($this->products->lockForUpdate(array_keys($quantities)) as $product) {
            $products[$product->id] = $product;
        }

        foreach ($quantities as $productId => $quantity) {
            $product = $products[$productId] ?? throw new ProductNotFoundException($productId);

            if ($product->availableQuantity() < $quantity) {
                throw new InsufficientStockException($productId);
            }
        }

        foreach ($quantities as $productId => $quantity) {
            if (!$this->products->reserve($productId, $quantity)) {
                throw new InsufficientStockException($productId);
            }
        }

        $this->recordChange($quantities);

        return array_values($products);
    }

    /**
     * Gives back reserved stock, for example when an order is cancelled or expires.
     * Callers must run it inside a transaction; rows are updated in ascending id order.
     *
     * @param array<int, int> $quantities quantities keyed by product id
     */
    public function release(array $quantities): void
    {
        $this->settle($quantities, $this->products->release(...));
    }

    /**
     * Turns reserved stock into sold stock once an order is paid. Callers must run it
     * inside a transaction; rows are updated in ascending id order.
     *
     * @param array<int, int> $quantities quantities keyed by product id
     */
    public function sell(array $quantities): void
    {
        $this->settle($quantities, $this->products->sellReserved(...));
    }

    /**
     * @param array<int, int> $quantities
     * @param callable(int, int): bool $settleProduct
     */
    private function settle(array $quantities, callable $settleProduct): void
    {
        ksort($quantities);

        foreach ($quantities as $productId => $quantity) {
            if (!$settleProduct($productId, $quantity)) {
                throw new LogicException(sprintf(self::RESERVATION_INCONSISTENCY_FORMAT, $productId));
            }
        }

        $this->recordChange($quantities);
    }

    /** @param array<int, int> $quantities */
    private function recordChange(array $quantities): void
    {
        $this->outbox->add(new ProductStockChangedMessage(array_keys($quantities)), $this->clock->now());
    }
}
