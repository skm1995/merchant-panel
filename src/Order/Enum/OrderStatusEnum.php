<?php

declare(strict_types=1);

namespace App\Order\Enum;

enum OrderStatusEnum: string
{
    case PENDING_PAYMENT = 'pending_payment';
}
