<?php

declare(strict_types=1);

namespace App\Payment\Gateway;

use App\Payment\Enum\PaymentOutcomeEnum;
use Money\Money;

/**
 * Provider-independent result reported by a verified payment webhook.
 */
final readonly class PaymentWebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $providerPaymentId,
        public PaymentOutcomeEnum $outcome,
        public Money $amount,
    ) {
    }
}
