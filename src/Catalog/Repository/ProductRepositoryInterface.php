<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    /**
     * Returns products ordered by id, starting after the given id (keyset pagination).
     *
     * @return list<Product>
     */
    public function findPage(int $limit, ?int $afterId): array;

    /**
     * Locks the products with FOR UPDATE until the surrounding transaction ends,
     * in ascending id order, and returns them ordered by id.
     *
     * @param list<int> $ids
     * @return list<Product>
     */
    public function lockForUpdate(array $ids): array;

    /**
     * Atomically reserves the quantity when that much stock is still available.
     */
    public function reserve(int $productId, int $quantity): bool;

    /**
     * Atomically gives back reserved stock; false when fewer units are reserved.
     */
    public function release(int $productId, int $quantity): bool;

    /**
     * Atomically turns reserved units into sold units, lowering both stock and reservations.
     */
    public function sellReserved(int $productId, int $quantity): bool;
}
