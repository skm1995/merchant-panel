<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Cache;

use App\Catalog\Entity\Product;
use App\Catalog\Repository\PdoProductRepository;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Shared\DependencyInjection\Autowire;

/**
 * Decorator that reads single products through the Redis cache. Pages, row locks, and
 * stock changes always go to the database, because they must see committed data.
 */
final class CachedProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        #[Autowire(PdoProductRepository::class)] private readonly ProductRepositoryInterface $products,
        private readonly ProductCache $cache,
    ) {
    }

    public function findById(int $id): ?Product
    {
        return $this->cache->remember($id, $this->products->findById(...));
    }

    public function findPage(int $limit, ?int $afterId): array
    {
        return $this->products->findPage($limit, $afterId);
    }

    public function lockForUpdate(array $ids): array
    {
        return $this->products->lockForUpdate($ids);
    }

    public function reserve(int $productId, int $quantity): bool
    {
        return $this->products->reserve($productId, $quantity);
    }

    public function release(int $productId, int $quantity): bool
    {
        return $this->products->release($productId, $quantity);
    }

    public function commit(int $productId, int $quantity): bool
    {
        return $this->products->commit($productId, $quantity);
    }
}
