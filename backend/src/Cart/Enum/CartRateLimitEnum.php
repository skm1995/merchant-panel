<?php

declare(strict_types=1);

namespace App\Cart\Enum;

/**
 * Environment variables with the rate limits of the cart routes.
 */
enum CartRateLimitEnum: string
{
    case CREATION = 'CART_CREATION_RATE_LIMIT';
}
