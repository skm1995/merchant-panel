<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Entity;

use App\Order\Entity\Order;
use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use InvalidArgumentException;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Order\OrderFixtures;

final class OrderTest extends TestCase
{
    public function testPlacedOrderAwaitsPaymentWithFrozenTotal(): void
    {
        $order = Order::place(
            OrderFixtures::orderId(),
            CartFixtures::cartId(),
            OrderFixtures::IDEMPOTENCY_KEY,
            [OrderFixtures::line(1, 2, 1000), OrderFixtures::line(2, 1, 499)],
            new DateTimeImmutable(OrderFixtures::RESERVATION_EXPIRES_AT),
        );

        self::assertSame(OrderStatusEnum::PENDING_PAYMENT, $order->status);
        self::assertTrue(Money::PLN(2499)->equals($order->total()));
    }

    public function testOrderNeedsAtLeastOneLine(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Order::place(
            OrderFixtures::orderId(),
            CartFixtures::cartId(),
            OrderFixtures::IDEMPOTENCY_KEY,
            [],
            new DateTimeImmutable(OrderFixtures::RESERVATION_EXPIRES_AT),
        );
    }
}
