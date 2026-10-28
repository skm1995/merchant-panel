<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\DependencyInjection\Env;

/**
 * The key that lets the Black Friday simulator send many requests from one address: a
 * request carrying it in X-Load-Test-Key skips the rate limits, and nothing else. The key
 * counts only from inside the server's networks, so even a leaked key gives nobody on the
 * internet a way around the limits. bin/deploy draws a new random key on every deployment.
 */
final readonly class LoadTestKey
{
    private const int PUBLIC_ADDRESS = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

    public function __construct(#[Env('LOAD_TEST_KEY')] private string $value)
    {
    }

    public function exempts(?string $candidate, ?string $clientAddress): bool
    {
        return $this->value !== ''
            && $candidate !== null
            && hash_equals($this->value, $candidate)
            && self::isInternal($clientAddress);
    }

    /**
     * A private or reserved address, such as the Docker network the simulator calls from;
     * Caddy passes the real client address, which a request cannot choose.
     */
    private static function isInternal(?string $address): bool
    {
        return $address !== null
            && filter_var($address, FILTER_VALIDATE_IP) !== false
            && filter_var($address, FILTER_VALIDATE_IP, self::PUBLIC_ADDRESS) === false;
    }
}
