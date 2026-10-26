<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Order\Entity\OrderId;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\Gateway\GatewayPayment;
use Money\Money;

/**
 * Write model of a payment attempt for an order at a payment provider.
 */
final readonly class Payment
{
    private function __construct(
        public PaymentId $id,
        public OrderId $orderId,
        public PaymentProviderEnum $provider,
        public string $providerPaymentId,
        public string $checkoutUrl,
        public Money $amount,
        public PaymentStatusEnum $status,
        public string $idempotencyKey,
    ) {
    }

    public static function start(
        PaymentId $id,
        OrderId $orderId,
        PaymentProviderEnum $provider,
        GatewayPayment $gatewayPayment,
        Money $amount,
        string $idempotencyKey,
    ): self {
        return new self(
            $id,
            $orderId,
            $provider,
            $gatewayPayment->providerPaymentId,
            $gatewayPayment->checkoutUrl,
            $amount,
            PaymentStatusEnum::PENDING,
            $idempotencyKey,
        );
    }
}
