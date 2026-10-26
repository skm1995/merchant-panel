<?php

declare(strict_types=1);

namespace App\Payment\Enum;

enum PaymentStatusEnum: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case REFUND_REQUIRED = 'refund_required';

    public function isSettled(): bool
    {
        return $this !== self::PENDING;
    }
}
