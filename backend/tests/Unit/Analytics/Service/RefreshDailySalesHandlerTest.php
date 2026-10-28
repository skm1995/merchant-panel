<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics\Service;

use App\Analytics\Repository\DailySalesRepositoryInterface;
use App\Analytics\Service\RefreshDailySalesHandler;
use App\Order\Message\OrderPaidMessage;
use App\Shared\Database\Enum\IsolationLevelEnum;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class RefreshDailySalesHandlerTest extends TestCase
{
    private const string DAY = '2026-10-27';

    public function testRefreshesTheDayThePaidOrderWasPlacedOnWithoutLockingOrders(): void
    {
        $sales = $this->createMock(DailySalesRepositoryInterface::class);
        $sales->method('findPlacementDay')->with(OrderFixtures::orderId())->willReturn(new DateTimeImmutable(self::DAY));
        $sales->expects(self::once())->method('refreshDay')->with(new DateTimeImmutable(self::DAY));
        $transactions = new ImmediateTransactionRunner();

        new RefreshDailySalesHandler($sales, $transactions)->handle(new OrderPaidMessage(OrderFixtures::orderId()));

        self::assertSame(IsolationLevelEnum::READ_COMMITTED, $transactions->lastIsolationLevel);
    }

    public function testIgnoresAnOrderThatNoLongerExists(): void
    {
        $sales = $this->createMock(DailySalesRepositoryInterface::class);
        $sales->method('findPlacementDay')->willReturn(null);
        $sales->expects(self::never())->method('refreshDay');

        new RefreshDailySalesHandler($sales, new ImmediateTransactionRunner())->handle(new OrderPaidMessage(OrderFixtures::orderId()));
    }
}
