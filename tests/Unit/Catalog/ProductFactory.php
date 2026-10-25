<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Catalog\Entity\Product;
use Money\Money;

final class ProductFactory
{
    private const string SKU_FORMAT = 'SKU-%d';
    private const string NAME_FORMAT = 'Product %d';
    private const int PRICE_IN_GROSZE = 1999;
    private const int STOCK = 10;
    private const int VERSION = 1;

    public static function create(int $id, int $stock = self::STOCK, int $reserved = 0): Product
    {
        return new Product(
            $id,
            sprintf(self::SKU_FORMAT, $id),
            sprintf(self::NAME_FORMAT, $id),
            Money::PLN(self::PRICE_IN_GROSZE),
            $stock,
            $reserved,
            self::VERSION,
        );
    }
}
