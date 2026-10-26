<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Service;

use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderStatusChange;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\FrozenClock;

final class OrderStatusChangeTest extends TestCase
{
    private const int VERSION = 2;
    private const int PRODUCT_ID = 5;
    private const int QUANTITY = 2;

    /** @return iterable<string, array{OrderStatusEnum, non-empty-string}> */
    public static function stockEffects(): iterable
    {
        yield 'paid commits the reservation' => [OrderStatusEnum::PAID, 'sellReserved'];
        yield 'cancelled releases it' => [OrderStatusEnum::CANCELLED, 'release'];
        yield 'expired releases it' => [OrderStatusEnum::EXPIRED, 'release'];
    }

    /** @param non-empty-string $stockMethod */
    #[DataProvider('stockEffects')]
    public function testChangesAPendingOrderAndAppliesItsStockEffect(OrderStatusEnum $to, string $stockMethod): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->expects(self::once())->method('transition')
            ->with(OrderFixtures::orderId(), OrderStatusEnum::PENDING_PAYMENT, $to, self::VERSION)
            ->willReturn(true);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method($stockMethod)->with(self::PRODUCT_ID, self::QUANTITY)->willReturn(true);

        self::assertTrue($this->change($orders, $products)->change(self::state(OrderStatusEnum::PENDING_PAYMENT), $to));
    }

    public function testRefusesAChangeTheStatusDoesNotAllow(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->expects(self::never())->method('transition');

        self::assertFalse($this->change($orders)->change(self::state(OrderStatusEnum::CANCELLED), OrderStatusEnum::PAID));
    }

    public function testLeavesStockAloneWhenTheOrderChangedConcurrently(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('transition')->willReturn(false);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('release');

        self::assertFalse($this->change($orders, $products)->change(self::state(OrderStatusEnum::PENDING_PAYMENT), OrderStatusEnum::EXPIRED));
    }

    private static function state(OrderStatusEnum $status): OrderState
    {
        return new OrderState(OrderFixtures::orderId(), $status, self::VERSION, [self::PRODUCT_ID => self::QUANTITY]);
    }

    private function change(OrderRepositoryInterface $orders, ?ProductRepositoryInterface $products = null): OrderStatusChange
    {
        return new OrderStatusChange(
            $orders,
            new StockReservation(
                $products ?? $this->createStub(ProductRepositoryInterface::class),
                $this->createStub(OutboxInterface::class),
                new FrozenClock(),
            ),
        );
    }
}
