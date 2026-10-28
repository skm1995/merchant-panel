<?php

declare(strict_types=1);

namespace App\Payment\ValueObject;

/**
 * How FakePay signs a webhook: an HMAC-SHA256 of the raw body with the shared secret, in hex,
 * in the X-FakePay-Signature header. The gateway verifies it; the Black Friday simulator,
 * acting as the provider, creates it.
 */
final class FakePaySignature
{
    public const string HEADER = 'x-fakepay-signature';
    private const string HASH_ALGORITHM = 'sha256';

    public static function of(string $payload, string $secret): string
    {
        return hash_hmac(self::HASH_ALGORITHM, $payload, $secret);
    }

    public static function matches(string $payload, string $secret, string $signature): bool
    {
        return hash_equals(self::of($payload, $secret), $signature);
    }
}
