<?php

declare(strict_types=1);

namespace App\Payment\Gateway;

use App\Payment\Enum\PaymentProviderEnum;

interface PaymentGatewayFactoryInterface
{
    public function create(PaymentProviderEnum $provider): PaymentGatewayInterface;
}
