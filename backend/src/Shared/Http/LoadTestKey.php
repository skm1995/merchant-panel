<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\DependencyInjection\Env;
use App\Shared\Http\Enum\HeaderNameEnum;

/**
 * The key that identifies the Black Friday simulator's requests: a request carrying it in
 * X-Load-Test-Key skips the rate limits, and its checkout may use the simulation's reserved
 * order keys. The key counts only from inside the server's networks, so even a leaked key
 * gives nobody on the internet either. bin/deploy draws a new random key on every deployment.
 */
final readonly class LoadTestKey
{
    private const int PUBLIC_ADDRESS = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

    public function __construct(#[Env('LOAD_TEST_KEY')] private string $value)
    {
    }

    public function identifiesSimulator(Request $request): bool
    {
        $candidate = $request->header(HeaderNameEnum::LOAD_TEST_KEY->value);

        return $this->value !== ''
            && $candidate !== null
            && hash_equals($this->value, $candidate)
            && self::isInternal($request->clientAddress);
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
