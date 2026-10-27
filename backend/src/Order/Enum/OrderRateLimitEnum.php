<?php

declare(strict_types=1);

namespace App\Order\Enum;

/**
 * Environment variables with the rate limits of the order routes.
 */
enum OrderRateLimitEnum: string
{
    case CHECKOUT = 'CHECKOUT_RATE_LIMIT';
}
