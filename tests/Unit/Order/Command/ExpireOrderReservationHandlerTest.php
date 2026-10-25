<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Command;

use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use App\Order\Command\ExpireOrderReservationCommand;
use App\Order\Command\ExpireOrderReservationHandler;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Repository\OrderRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Support\FrozenClock;

final class ExpireOrderReservationHandlerTest extends TestCase
{
    private const int VERSION = 1;

    public function testExpiresUnpaidOrderAndReleasesItsStock(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->expects(self::once())->method('transition')
            ->with(OrderFixtures::orderId(), OrderStatusEnum::PENDING_PAYMENT, OrderStatusEnum::EXPIRED, self::VERSION)
            ->willReturn(true);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method('release')->with(5, 2)->willReturn(true);
        $transactions = new ImmediateTransactionRunner();

        new ExpireOrderReservationHandler($orders, new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock()), $transactions)
            ->handle(new ExpireOrderReservationCommand(OrderFixtures::orderId()));

        self::assertSame(IsolationLevelEnum::READ_COMMITTED, $transactions->lastIsolationLevel);
    }

    public function testLeavesPaidOrCancelledOrdersAlone(): void
    {
        foreach ([OrderStatusEnum::PAID, OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED] as $status) {
            $orders = $this->createMock(OrderRepositoryInterface::class);
            $orders->method('findState')->willReturn(self::state($status));
            $orders->expects(self::never())->method('transition');

            $this->handler($orders)->handle(new ExpireOrderReservationCommand(OrderFixtures::orderId()));
        }
    }

    public function testDoesNotReleaseStockWhenAPaymentWonTheRace(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(self::state(OrderStatusEnum::PENDING_PAYMENT));
        $orders->method('transition')->willReturn(false);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('release');

        new ExpireOrderReservationHandler($orders, new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock()), new ImmediateTransactionRunner())
            ->handle(new ExpireOrderReservationCommand(OrderFixtures::orderId()));
    }

    public function testIgnoresOrdersThatNoLongerExist(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(null);
        $orders->expects(self::never())->method('transition');

        $this->handler($orders)->handle(new ExpireOrderReservationCommand(OrderFixtures::orderId()));
    }

    public function testTravelsThroughTheOutboxUnderAStableTypeName(): void
    {
        $command = new ExpireOrderReservationCommand(OrderFixtures::orderId());

        self::assertSame('order.expire_reservation', ExpireOrderReservationCommand::messageType());
        self::assertSame(OrderFixtures::ORDER_ID, ExpireOrderReservationCommand::fromArray($command->toArray())->orderId->value);
    }

    private static function state(OrderStatusEnum $status): OrderState
    {
        return new OrderState(OrderFixtures::orderId(), $status, self::VERSION, [5 => 2]);
    }

    private function handler(OrderRepositoryInterface $orders): ExpireOrderReservationHandler
    {
        return new ExpireOrderReservationHandler(
            $orders,
            new StockReservation($this->createStub(ProductRepositoryInterface::class), $this->createStub(OutboxInterface::class), new FrozenClock()),
            new ImmediateTransactionRunner(),
        );
    }
}
