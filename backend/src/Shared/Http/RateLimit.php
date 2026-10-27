<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Attribute;

/**
 * Limits how often one client may call a route. The environment variable holds the policy as
 * "<requests>/<seconds>", for example 10/300; the router answers clients over it with 429.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RateLimit
{
    public function __construct(public string $environmentKey)
    {
    }
}
