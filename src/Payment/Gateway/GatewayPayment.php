<?php

declare(strict_types=1);

namespace App\Payment\Gateway;

final readonly class GatewayPayment
{
    public function __construct(public string $providerPaymentId, public string $checkoutUrl)
    {
    }
}
