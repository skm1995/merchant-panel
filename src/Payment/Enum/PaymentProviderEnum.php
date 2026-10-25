<?php

declare(strict_types=1);

namespace App\Payment\Enum;

enum PaymentProviderEnum: string
{
    case FAKEPAY = 'fakepay';
    case QUICKPAY = 'quickpay';
}
