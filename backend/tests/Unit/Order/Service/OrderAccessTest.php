<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Service;

use App\Order\Entity\OrderId;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Service\OrderAccess;
use PHPUnit\Framework\TestCase;

final class OrderAccessTest extends TestCase
{
    private const string SECRET = 'order-access-secret';
    private const string OTHER_SECRET = 'another-secret';
    private const string TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public function testIssuesATokenThatOpensOnlyItsOwnOrder(): void
    {
        $access = new OrderAccess(self::SECRET);
        $orderId = OrderId::generate();
        $token = $access->tokenFor($orderId);

        $access->verify($orderId, $token);

        self::assertMatchesRegularExpression(self::TOKEN_PATTERN, $token);
        $this->expectException(OrderNotFoundException::class);
        $access->verify(OrderId::generate(), $token);
    }

    public function testIssuesTheSameTokenAgainSoARepeatedCheckoutReturnsIt(): void
    {
        $orderId = OrderId::generate();

        self::assertSame(new OrderAccess(self::SECRET)->tokenFor($orderId), new OrderAccess(self::SECRET)->tokenFor($orderId));
    }

    public function testRefusesATokenIssuedWithAnotherSecret(): void
    {
        $orderId = OrderId::generate();

        $this->expectException(OrderNotFoundException::class);
        new OrderAccess(self::SECRET)->verify($orderId, new OrderAccess(self::OTHER_SECRET)->tokenFor($orderId));
    }
}
