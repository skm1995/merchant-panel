<?php

declare(strict_types=1);

namespace App\Auth\Enum;

/**
 * Environment variables with the rate limits of the authentication routes.
 */
enum AuthRateLimitEnum: string
{
    case SIGN_IN = 'SIGN_IN_RATE_LIMIT';
    case REGISTRATION = 'REGISTRATION_RATE_LIMIT';
}
