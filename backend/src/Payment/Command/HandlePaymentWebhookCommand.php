<?php

declare(strict_types=1);

namespace App\Payment\Command;

use App\Payment\Enum\PaymentProviderEnum;

final readonly class HandlePaymentWebhookCommand
{
    /** @param array<string, string> $headers request headers keyed by lowercase name */
    public function __construct(
        public PaymentProviderEnum $provider,
        public string $payload,
        public array $headers,
    ) {
    }
}
