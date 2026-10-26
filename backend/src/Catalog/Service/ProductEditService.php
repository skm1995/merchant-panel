<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Exception\ProductVersionMismatchException;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\Messaging\Outbox\OutboxInterface;
use Money\Money;
use Psr\Clock\ClockInterface;

/**
 * Lets a merchant change a product's price and stock with optimistic locking: the change
 * applies only to the version the merchant edited, and cached copies are invalidated
 * through the outbox once it is committed.
 */
final readonly class ProductEditService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private OutboxInterface $outbox,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {
    }

    public function edit(int $productId, Money $price, int $stock, int $expectedVersion): Product
    {
        return $this->transactions->run(function () use ($productId, $price, $stock, $expectedVersion): Product {
            $product = $this->products->lockForUpdate([$productId])[0] ?? throw new ProductNotFoundException($productId);

            if ($product->version !== $expectedVersion) {
                throw new ProductVersionMismatchException();
            }

            $changed = $product->withPriceAndStock($price, $stock);

            if (!$this->products->update($changed, $expectedVersion)) {
                throw new ProductVersionMismatchException();
            }

            $this->outbox->add(new ProductStockChangedMessage([$productId]), $this->clock->now());

            return new Product($changed->id, $changed->sku, $changed->name, $changed->price, $changed->stock, $changed->reserved, $expectedVersion + 1);
        }, IsolationLevelEnum::READ_COMMITTED);
    }
}
