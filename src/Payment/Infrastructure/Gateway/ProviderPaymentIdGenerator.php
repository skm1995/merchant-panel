<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

/**
 * Generates provider-side payment ids, as a real provider would return them.
 */
final class ProviderPaymentIdGenerator
{
    private const int RANDOM_BYTES = 16;

    public static function generate(string $prefix): string
    {
        return $prefix . (random_bytes(self::RANDOM_BYTES) |> bin2hex(...));
    }
}
