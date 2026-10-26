<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Command;

use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use App\Order\Command\CancelOrderCommand;
use App\Order\Command\CancelOrderHandler;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\InvalidOrderTransitionException;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Exception\OrderVersionMismatchException;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderStatusChange;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class CancelOrderHandlerTest extends TestCase
{
    private const int VERSION = 3;

    public function testCancelsPendingOrderAndReleasesItsReservation(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->expects(self::once())->method('transition')
            ->with(OrderFixtures::orderId(), OrderStatusEnum::PENDING_PAYMENT, OrderStatusEnum::CANCELLED, self::VERSION)
            ->willReturn(true);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method('release')->with(5, 2)->willReturn(true);

        $this->handler($orders, $products)->handle(new CancelOrderCommand(OrderFixtures::orderId(), self::VERSION));
    }

    public function testStaleVersionIsRejectedBeforeAnyChange(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->expects(self::never())->method('transition');

        $this->expectException(OrderVersionMismatchException::class);
        $this->handler($orders)->handle(new CancelOrderCommand(OrderFixtures::orderId(), self::VERSION - 1));
    }

    public function testLosingTheConditionalUpdateRaceIsAVersionMismatch(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->method('transition')->willReturn(false);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('release');

        $this->expectException(OrderVersionMismatchException::class);
        $this->handler($orders, $products)->handle(new CancelOrderCommand(OrderFixtures::orderId(), self::VERSION));
    }

    public function testPaidOrderCannotBeCancelled(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PAID));

        $this->expectException(InvalidOrderTransitionException::class);
        $this->handler($orders)->handle(new CancelOrderCommand(OrderFixtures::orderId(), self::VERSION));
    }

    public function testReportsMissingOrder(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->handler($orders)->handle(new CancelOrderCommand(OrderFixtures::orderId(), self::VERSION));
    }

    private static function state(OrderStatusEnum $status): OrderState
    {
        return new OrderState(OrderFixtures::orderId(), $status, self::VERSION, [5 => 2]);
    }

    private function handler(OrderRepositoryInterface $orders, ?ProductRepositoryInterface $products = null): CancelOrderHandler
    {
        return new CancelOrderHandler(
            $orders,
            new OrderStatusChange(
                $orders,
                new StockReservation($products ?? $this->createStub(ProductRepositoryInterface::class), $this->createStub(OutboxInterface::class), new FrozenClock()),
            ),
            new ImmediateTransactionRunner(),
        );
    }
}
