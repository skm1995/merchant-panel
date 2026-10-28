<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Message;

use App\Order\Message\OrderPaidMessage;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class OrderPaidMessageTest extends TestCase
{
    public function testTravelsThroughTheOutboxUnderAStableTypeName(): void
    {
        $restored = OrderPaidMessage::fromArray(new OrderPaidMessage(OrderFixtures::orderId())->toArray());

        self::assertSame('order.paid', OrderPaidMessage::messageType());
        self::assertSame(OrderFixtures::ORDER_ID, $restored->orderId->value);
    }
}
