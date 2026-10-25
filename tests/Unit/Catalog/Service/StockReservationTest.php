<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Service;

use App\Catalog\Exception\InsufficientStockException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\FrozenClock;

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

        $locked = new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->reserve([8 => 1, 3 => 2]);

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
        new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->reserve([3 => 1, 8 => 2]);
    }

    public function testRejectsUnknownProducts(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(3)]);

        $this->expectException(ProductNotFoundException::class);
        new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->reserve([3 => 1, 9 => 1]);
    }

    public function testTreatsALostGuardedUpdateAsInsufficientStock(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(3)]);
        $products->method('reserve')->willReturn(false);

        $this->expectException(InsufficientStockException::class);
        new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->reserve([3 => 1]);
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

        new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->release([8 => 1, 3 => 2]);

        self::assertSame([[3, 2], [8, 1]], $released);
    }

    public function testReleasingMoreThanReservedIsAnInconsistency(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('release')->willReturn(false);

        $this->expectException(\LogicException::class);
        new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->release([3 => 1]);
    }

    public function testCommitsReservationsAsSoldStockInIdOrder(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $committed = [];
        $products->expects(self::exactly(2))
            ->method('commit')
            ->willReturnCallback(static function (int $productId, int $quantity) use (&$committed): bool {
                $committed[] = [$productId, $quantity];

                return true;
            });

        new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())->commit([8 => 1, 3 => 2]);

        self::assertSame([[3, 2], [8, 1]], $committed);
    }

    public function testRecordsWhichProductsChangedInTheOutbox(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(3), ProductFactory::create(8)]);
        $products->method('reserve')->willReturn(true);
        $products->method('release')->willReturn(true);
        $products->method('commit')->willReturn(true);
        $recorded = [];
        $outbox = $this->createMock(OutboxInterface::class);
        $outbox->expects(self::exactly(3))->method('add')->willReturnCallback(
            static function (ProductStockChangedMessage $message) use (&$recorded): void {
                $recorded[] = $message->productIds;
            },
        );
        $reservation = new StockReservation($products, $outbox, new FrozenClock());

        $reservation->reserve([8 => 1, 3 => 2]);
        $reservation->release([8 => 1]);
        $reservation->commit([3 => 2]);

        self::assertSame([[3, 8], [8], [3]], $recorded);
    }

    public function testRecordsNothingWhenTheReservationFails(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(3, 1)]);
        $outbox = $this->createMock(OutboxInterface::class);
        $outbox->expects(self::never())->method('add');

        $this->expectException(InsufficientStockException::class);
        new StockReservation($products, $outbox, new FrozenClock())->reserve([3 => 2]);
    }
}
