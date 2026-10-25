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
}
