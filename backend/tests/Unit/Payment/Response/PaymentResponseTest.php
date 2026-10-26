<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Response;

use App\Payment\Response\PaymentResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Payment\PaymentFixtures;

final class PaymentResponseTest extends TestCase
{
    public function testDescribesPaymentAndWhereToPay(): void
    {
        self::assertSame([
            'id' => PaymentFixtures::PAYMENT_ID,
            'order_id' => OrderFixtures::ORDER_ID,
            'provider' => 'fakepay',
            'status' => 'pending',
            'amount' => ['amount' => '2500', 'currency' => 'PLN'],
            'checkout_url' => PaymentFixtures::CHECKOUT_URL,
        ], PaymentResponse::fromView(PaymentFixtures::view())->toArray());
    }
}
