<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Query;

use App\Payment\Exception\PaymentNotFoundException;
use App\Payment\Query\GetPaymentHandler;
use App\Payment\Query\GetPaymentQuery;
use App\Payment\Repository\PaymentReadRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Payment\PaymentFixtures;

final class GetPaymentHandlerTest extends TestCase
{
    public function testReturnsPaymentView(): void
    {
        $payments = $this->createStub(PaymentReadRepositoryInterface::class);
        $payments->method('find')->willReturn(PaymentFixtures::view());

        self::assertSame(
            PaymentFixtures::PAYMENT_ID,
            new GetPaymentHandler($payments)->handle(new GetPaymentQuery(PaymentFixtures::paymentId()))->id->value,
        );
    }

    public function testReportsMissingPayment(): void
    {
        $payments = $this->createStub(PaymentReadRepositoryInterface::class);
        $payments->method('find')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);
        new GetPaymentHandler($payments)->handle(new GetPaymentQuery(PaymentFixtures::paymentId()));
    }
}
