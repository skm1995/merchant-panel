<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Exception\ProductVersionMismatchException;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductEditService;
use App\Shared\Messaging\Outbox\OutboxInterface;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class ProductEditServiceTest extends TestCase
{
    private const int PRODUCT_ID = 5;
    private const int STOCK = 12;

    public function testSavesThePriceAndStockAndInvalidatesCachedCopies(): void
    {
        $product = ProductFactory::create(self::PRODUCT_ID);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->with([self::PRODUCT_ID])->willReturn([$product]);
        $products->expects(self::once())->method('update')
            ->with(self::callback(static fn (Product $changed): bool => $changed->stock === self::STOCK), $product->version)
            ->willReturn(true);
        $outbox = $this->createMock(OutboxInterface::class);
        $outbox->expects(self::once())->method('add')->with(new ProductStockChangedMessage([self::PRODUCT_ID]));

        $edited = $this->service($products, $outbox)->edit(self::PRODUCT_ID, Money::PLN(2500), self::STOCK, $product->version);

        self::assertSame($product->version + 1, $edited->version);
        self::assertTrue(Money::PLN(2500)->equals($edited->price));
    }

    public function testRejectsAChangeBasedOnAnOldVersion(): void
    {
        $product = ProductFactory::create(self::PRODUCT_ID);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([$product]);
        $products->expects(self::never())->method('update');

        $this->expectException(ProductVersionMismatchException::class);
        $this->service($products)->edit(self::PRODUCT_ID, Money::PLN(2500), self::STOCK, $product->version - 1);
    }

    public function testRejectsAChangeThatLostARace(): void
    {
        $product = ProductFactory::create(self::PRODUCT_ID);
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([$product]);
        $products->method('update')->willReturn(false);

        $this->expectException(ProductVersionMismatchException::class);
        $this->service($products)->edit(self::PRODUCT_ID, Money::PLN(2500), self::STOCK, $product->version);
    }

    public function testReportsAMissingProduct(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([]);

        $this->expectException(ProductNotFoundException::class);
        $this->service($products)->edit(self::PRODUCT_ID, Money::PLN(2500), self::STOCK, 1);
    }

    private function service(ProductRepositoryInterface $products, ?OutboxInterface $outbox = null): ProductEditService
    {
        return new ProductEditService($products, $outbox ?? $this->createStub(OutboxInterface::class), new ImmediateTransactionRunner(), new FrozenClock());
    }
}
