<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Product;

final readonly class ProductPage
{
    /** @param list<Product> $products */
    public function __construct(public array $products, public ?int $nextCursor)
    {
    }
}
