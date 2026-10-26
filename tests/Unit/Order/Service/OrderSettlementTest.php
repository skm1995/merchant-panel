<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Service;

use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderSettlement;
use App\Order\Service\OrderStatusChange;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\FrozenClock;

final class OrderSettlementTest extends TestCase
{
    private const int VERSION = 2;

    public function testMarksPendingOrderPaidAndCommitsItsStock(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->expects(self::once())->method('transition')
            ->with(OrderFixtures::orderId(), OrderStatusEnum::PENDING_PAYMENT, OrderStatusEnum::PAID, self::VERSION)
            ->willReturn(true);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method('commit')->with(5, 2)->willReturn(true);

        self::assertTrue(new OrderSettlement($orders, new OrderStatusChange($orders, new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())))->markPaid(OrderFixtures::orderId()));
    }

    public function testCancelledOrderIsNotPaid(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::CANCELLED));
        $orders->expects(self::never())->method('transition');

        self::assertFalse($this->settlement($orders)->markPaid(OrderFixtures::orderId()));
    }

    public function testOrderChangedConcurrentlyIsNotPaid(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->method('transition')->willReturn(false);

        self::assertFalse($this->settlement($orders)->markPaid(OrderFixtures::orderId()));
    }

    public function testReportsMissingOrder(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->settlement($orders)->markPaid(OrderFixtures::orderId());
    }

    private static function state(OrderStatusEnum $status): OrderState
    {
        return new OrderState(OrderFixtures::orderId(), $status, self::VERSION, [5 => 2]);
    }

    private function settlement(OrderRepositoryInterface $orders): OrderSettlement
    {
        return new OrderSettlement($orders, new OrderStatusChange($orders, new StockReservation($this->createStub(ProductRepositoryInterface::class), $this->createStub(OutboxInterface::class), new FrozenClock())));
    }
}
