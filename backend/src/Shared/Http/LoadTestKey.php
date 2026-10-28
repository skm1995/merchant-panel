<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\DependencyInjection\Env;

/**
 * The key that lets the Black Friday simulator and the load test send many requests from one
 * address: a request carrying it in X-Load-Test-Key skips the rate limits, and nothing else.
 * bin/deploy draws a new random key on every deployment.
 */
final readonly class LoadTestKey
{
    public function __construct(#[Env('LOAD_TEST_KEY')] private string $value)
    {
    }

    public function matches(?string $candidate): bool
    {
        return $this->value !== '' && $candidate !== null && hash_equals($this->value, $candidate);
    }
}
