<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use Closure;
use Psr\Clock\ClockInterface;

/**
 * Read-through cache that prevents cache stampedes.
 *
 * Only the process holding a short Redis lock recomputes a value; others keep serving the
 * current value or, on a cold cache, wait briefly for the lock holder. Values are also
 * recomputed early with probabilistic early expiration (XFetch): the closer a value is to
 * expiry and the longer it took to compute, the likelier one request refreshes it first.
 */
final class StampedeProtectedCache
{
    private const float XFETCH_BETA = 1.0;
    private const int LOCK_TTL_MILLISECONDS = 5000;
    private const int DEFAULT_WAIT_STEP_MICROSECONDS = 50000;
    private const int MAXIMUM_WAIT_STEPS = 40;
    private const string LOCK_KEY_FORMAT = '%s:lock';
    private const string TIMESTAMP_FORMAT = 'U.u';

    /** @var Closure(): float */
    private readonly Closure $random;

    /**
     * @param (Closure(): float)|null $random returns a number in (0, 1]
     */
    public function __construct(
        private readonly CacheStoreInterface $store,
        private readonly ClockInterface $clock,
        ?Closure $random = null,
        private readonly int $waitStepMicroseconds = self::DEFAULT_WAIT_STEP_MICROSECONDS,
    ) {
        $this->random = $random ?? static fn (): float => (mt_rand() + 1) / (mt_getrandmax() + 1);
    }

    /** @param callable(): string $compute */
    public function remember(string $key, int $ttlSeconds, callable $compute): string
    {
        $entry = $this->read($key);

        if ($entry !== null && !$this->shouldRecomputeEarly($entry)) {
            return $entry->value;
        }

        $lockKey = sprintf(self::LOCK_KEY_FORMAT, $key);
        $token = $this->store->lock($lockKey, self::LOCK_TTL_MILLISECONDS);

        if ($token === null && $entry !== null) {
            return $entry->value;
        }

        if ($token === null) {
            return $this->waitForValue($key) ?? $compute();
        }

        try {
            $startedAt = $this->now();
            $value = $compute();
            $finishedAt = $this->now();
            $this->store->set(
                $key,
                new CacheEntry($value, $finishedAt - $startedAt, $finishedAt + $ttlSeconds)->encode(),
                $ttlSeconds,
            );

            return $value;
        } finally {
            $this->store->unlock($lockKey, $token);
        }
    }

    private function shouldRecomputeEarly(CacheEntry $entry): bool
    {
        return $this->now() - $entry->delta * self::XFETCH_BETA * log(($this->random)()) >= $entry->expiresAt;
    }

    private function waitForValue(string $key): ?string
    {
        for ($step = 0; $step < self::MAXIMUM_WAIT_STEPS; $step++) {
            usleep($this->waitStepMicroseconds);
            $entry = $this->read($key);

            if ($entry !== null) {
                return $entry->value;
            }
        }

        return null;
    }

    private function read(string $key): ?CacheEntry
    {
        $encoded = $this->store->get($key);

        return $encoded === null ? null : CacheEntry::decode($encoded);
    }

    private function now(): float
    {
        return (float) $this->clock->now()->format(self::TIMESTAMP_FORMAT);
    }
}
