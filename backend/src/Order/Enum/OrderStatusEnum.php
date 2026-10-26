<?php

declare(strict_types=1);

namespace App\Order\Enum;

enum OrderStatusEnum: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::PENDING_PAYMENT && $next !== self::PENDING_PAYMENT;
    }
}
