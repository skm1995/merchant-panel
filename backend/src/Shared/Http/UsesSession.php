<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Attribute;

/**
 * Gives an API route the browser session: the session starts and state-changing requests
 * must send the CSRF token in the X-CSRF-Token header. Other API routes stay sessionless.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class UsesSession
{
}
