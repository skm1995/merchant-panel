<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Enum;

use App\Order\Enum\OrderStatusEnum;
use PHPUnit\Framework\TestCase;

final class OrderStatusEnumTest extends TestCase
{
    public function testPendingPaymentCanBecomePaidCancelledOrExpired(): void
    {
        foreach ([OrderStatusEnum::PAID, OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED] as $next) {
            self::assertTrue(OrderStatusEnum::PENDING_PAYMENT->canTransitionTo($next));
        }

        self::assertFalse(OrderStatusEnum::PENDING_PAYMENT->canTransitionTo(OrderStatusEnum::PENDING_PAYMENT));
    }

    public function testFinalStatusesNeverChange(): void
    {
        foreach ([OrderStatusEnum::PAID, OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED] as $final) {
            foreach (OrderStatusEnum::cases() as $next) {
                self::assertFalse($final->canTransitionTo($next));
            }
        }
    }
}
