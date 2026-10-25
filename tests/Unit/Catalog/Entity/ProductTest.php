<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Entity;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;

final class ProductTest extends TestCase
{
    private const int PRODUCT_ID = 1;

    public function testAvailableQuantityExcludesReservedStock(): void
    {
        self::assertSame(7, ProductFactory::create(self::PRODUCT_ID, 10, 3)->availableQuantity());
    }

    public function testRejectsReservationsExceedingStock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProductFactory::create(self::PRODUCT_ID, 2, 3);
    }

    public function testRejectsNegativeStock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProductFactory::create(self::PRODUCT_ID, -1);
    }
}
