<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Entity;

use InvalidArgumentException;
use App\Catalog\Exception\StockBelowReservationsException;
use Money\Money;
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

    public function testChangesPriceAndStockKeepingIdentityAndVersion(): void
    {
        $product = ProductFactory::create(self::PRODUCT_ID, 10, 3);

        $changed = $product->withPriceAndStock(Money::PLN(2500), 7);

        self::assertTrue(Money::PLN(2500)->equals($changed->price));
        self::assertSame(7, $changed->stock);
        self::assertSame(3, $changed->reserved);
        self::assertSame($product->version, $changed->version);
        self::assertSame($product->sku, $changed->sku);
    }

    public function testRefusesStockBelowTheReservedUnits(): void
    {
        $this->expectException(StockBelowReservationsException::class);

        ProductFactory::create(self::PRODUCT_ID, 10, 3)->withPriceAndStock(Money::PLN(2500), 2);
    }
}
