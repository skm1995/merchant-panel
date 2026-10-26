<?php

declare(strict_types=1);

namespace App\Payment\Enum;

enum PaymentOutcomeEnum: string
{
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
