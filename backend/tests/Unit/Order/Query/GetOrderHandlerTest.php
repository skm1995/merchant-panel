<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Query;

use App\Order\Exception\OrderNotFoundException;
use App\Order\Query\GetOrderHandler;
use App\Order\Query\GetOrderQuery;
use App\Order\Repository\OrderReadRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class GetOrderHandlerTest extends TestCase
{
    public function testReturnsOrderView(): void
    {
        $orders = $this->createStub(OrderReadRepositoryInterface::class);
        $orders->method('find')->willReturn(OrderFixtures::view());

        $view = new GetOrderHandler($orders, OrderFixtures::access())->handle(new GetOrderQuery(OrderFixtures::orderId(), OrderFixtures::accessToken()));

        self::assertSame(OrderFixtures::ORDER_ID, $view->id->value);
    }

    public function testReportsMissingOrder(): void
    {
        $orders = $this->createStub(OrderReadRepositoryInterface::class);
        $orders->method('find')->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        new GetOrderHandler($orders, OrderFixtures::access())->handle(new GetOrderQuery(OrderFixtures::orderId(), OrderFixtures::accessToken()));
    }

    public function testHidesTheOrderFromAWrongAccessToken(): void
    {
        $orders = $this->createStub(OrderReadRepositoryInterface::class);
        $orders->method('find')->willReturn(OrderFixtures::view());

        $this->expectException(OrderNotFoundException::class);
        new GetOrderHandler($orders, OrderFixtures::access())->handle(new GetOrderQuery(OrderFixtures::orderId(), OrderFixtures::WRONG_ACCESS_TOKEN));
    }
}
