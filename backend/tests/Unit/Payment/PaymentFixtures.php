<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use App\Payment\Entity\PaymentId;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\ReadModel\PaymentView;
use Money\Money;
use Tests\Unit\Order\OrderFixtures;

final class PaymentFixtures
{
    public const string PAYMENT_ID = '2c8f7e6d-5b4a-4398-a1b2-c3d4e5f60718';
    public const string PROVIDER_PAYMENT_ID = 'fp_0123456789abcdef';
    public const string CHECKOUT_URL = 'https://pay.example.test/pay/fp_0123456789abcdef';
    public const string IDEMPOTENCY_KEY = 'payment-key-1';
    public const string CHECKOUT_BASE_URL = 'https://pay.example.test';
    public const string WEBHOOK_SECRET = 'test-webhook-secret';

    public static function paymentId(): PaymentId
    {
        return PaymentId::fromString(self::PAYMENT_ID);
    }

    public static function view(PaymentStatusEnum $status = PaymentStatusEnum::PENDING): PaymentView
    {
        return new PaymentView(
            self::paymentId(),
            OrderFixtures::orderId(),
            PaymentProviderEnum::FAKEPAY,
            $status,
            Money::PLN(2500),
            self::CHECKOUT_URL,
        );
    }
}
