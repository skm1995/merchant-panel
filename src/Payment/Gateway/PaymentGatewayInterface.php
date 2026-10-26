<?php

declare(strict_types=1);

namespace App\Payment\Gateway;

use App\Payment\Entity\PaymentId;
use Money\Money;

/**
 * Strategy for one payment provider: each provider creates payments and signs webhooks its own way.
 */
interface PaymentGatewayInterface
{
    public function createPayment(PaymentId $paymentId, Money $amount): GatewayPayment;

    /**
     * Verifies the provider's signature and translates its webhook into a payment event.
     *
     * @param array<string, string> $headers request headers keyed by lowercase name
     * @throws \App\Payment\Exception\InvalidWebhookSignatureException
     * @throws \App\Payment\Exception\InvalidWebhookPayloadException
     */
    public function parseWebhook(string $payload, array $headers): PaymentWebhookEvent;
}
