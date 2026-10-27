<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Cache\CacheStoreInterface;
use App\Shared\Cache\Exception\CacheUnavailableException;

/**
 * Cache store for unit tests; another holder of a lock can be simulated with lockHeldElsewhere,
 * and an unreachable cache server with unavailable.
 */
final class InMemoryCacheStore implements CacheStoreInterface
{
    private const string TOKEN = 'test-lock-token';

    /** @var array<string, string> */
    public array $values = [];
    /** @var array<string, int> */
    public array $ttls = [];
    /** @var list<string> */
    public array $locked = [];
    public bool $lockHeldElsewhere = false;
    public bool $unavailable = false;
    /** @var array<string, int> */
    public array $counters = [];
    /** @var array<string, string> value that appears after the given key was read once */
    public array $valuesAppearingAfterRead = [];

    public function get(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        if ($value === null && isset($this->valuesAppearingAfterRead[$key])) {
            $this->values[$key] = $this->valuesAppearingAfterRead[$key];
            unset($this->valuesAppearingAfterRead[$key]);
        }

        return $value;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttlSeconds;
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        if ($this->unavailable) {
            throw new CacheUnavailableException();
        }

        $this->ttls[$key] ??= $ttlSeconds;

        return $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;
    }

    public function delete(array $keys): void
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }
    }

    public function lock(string $key, int $ttlMilliseconds): ?string
    {
        if ($this->lockHeldElsewhere || in_array($key, $this->locked, true)) {
            return null;
        }

        $this->locked[] = $key;

        return self::TOKEN;
    }

    public function unlock(string $key, string $token): void
    {
        $this->locked = array_values(array_filter($this->locked, static fn (string $locked): bool => $locked !== $key));
    }
}
