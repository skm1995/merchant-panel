<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Exception\InvalidPageSizeException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Repository\ProductRepositoryInterface;

final class ProductCatalog
{
    public const int DEFAULT_PAGE_SIZE = 20;
    public const int MAXIMUM_PAGE_SIZE = 100;

    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    public function page(int $size, ?int $afterId): ProductPage
    {
        if ($size < 1 || $size > self::MAXIMUM_PAGE_SIZE) {
            throw new InvalidPageSizeException(self::MAXIMUM_PAGE_SIZE);
        }

        $products = $this->products->findPage($size + 1, $afterId);
        $hasNextPage = count($products) > $size;
        $pageProducts = array_slice($products, 0, $size);

        return new ProductPage($pageProducts, $hasNextPage ? $pageProducts[$size - 1]->id : null);
    }

    public function ensureExists(int $id): void
    {
        $this->product($id);
    }

    public function product(int $id): Product
    {
        return $this->products->findById($id) ?? throw new ProductNotFoundException($id);
    }
}
