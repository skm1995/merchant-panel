<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Attribute;

/**
 * Restricts a route to signed-in users with access to the merchant panel; it implies
 * #[UsesSession].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RequireMerchant
{
}
