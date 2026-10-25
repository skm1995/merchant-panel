<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Service;

use App\Catalog\Exception\InsufficientStockException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;

final class StockReservationTest extends TestCase
{
    public function testLocksProductsInIdOrderAndReservesEveryQuantity(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())
            ->method('lockForUpdate')
            ->with([3, 8])
            ->willReturn([ProductFactory::create(3), ProductFactory::create(8)]);
        $reserved = [];
        $products->expects(self::exactly(2))
            ->method('reserve')
            ->willReturnCallback(static function (int $productId, int $quantity) use (&$reserved): bool {
                $reserved[$productId] = $quantity;

                return true;
            });

        $locked = new StockReservation($products)->reserve([8 => 1, 3 => 2]);

        self::assertSame([3 => 2, 8 => 1], $reserved);
        self::assertSame([3, 8], array_map(static fn ($product): int => $product->id, $locked));
    }

    public function testReservesNothingWhenAnyProductLacksStock(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([
            ProductFactory::create(3, 10),
            ProductFactory::create(8, 5, 4),
        ]);
        $products->expects(self::never())->method('reserve');

        $this->expectException(InsufficientStockException::class);
        new StockReservation($products)->reserve([3 => 1, 8 => 2]);
    }

    public function testRejectsUnknownProducts(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(3)]);

        $this->expectException(ProductNotFoundException::class);
        new StockReservation($products)->reserve([3 => 1, 9 => 1]);
    }

    public function testTreatsALostGuardedUpdateAsInsufficientStock(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(3)]);
        $products->method('reserve')->willReturn(false);

        $this->expectException(InsufficientStockException::class);
        new StockReservation($products)->reserve([3 => 1]);
    }

    public function testReleasesReservationsInIdOrder(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $released = [];
        $products->expects(self::exactly(2))
            ->method('release')
            ->willReturnCallback(static function (int $productId, int $quantity) use (&$released): bool {
                $released[] = [$productId, $quantity];

                return true;
            });

        new StockReservation($products)->release([8 => 1, 3 => 2]);

        self::assertSame([[3, 2], [8, 1]], $released);
    }

    public function testReleasingMoreThanReservedIsAnInconsistency(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('release')->willReturn(false);

        $this->expectException(\LogicException::class);
        new StockReservation($products)->release([3 => 1]);
    }
}
