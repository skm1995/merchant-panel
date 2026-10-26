<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Attribute;

/**
 * Declares that a route requires the X-Api-Key header to match the secret stored in the
 * given environment variable; the router answers missing or wrong keys with HTTP 401.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RequireApiKey
{
    public function __construct(public string $environmentKey)
    {
    }
}
