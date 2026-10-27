<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use App\Shared\Cache\Exception\CacheUnavailableException;

interface CacheStoreInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttlSeconds): void;

    /**
     * Adds one to a counter and returns the new count. The first increment starts the
     * counter's expiry; later ones keep it.
     *
     * @throws CacheUnavailableException when the cache server cannot be reached
     */
    public function increment(string $key, int $ttlSeconds): int;

    /** @param list<string> $keys */
    public function delete(array $keys): void;

    /**
     * Acquires an exclusive lock that expires on its own; returns the owner token or null
     * when someone else holds the lock.
     */
    public function lock(string $key, int $ttlMilliseconds): ?string;

    /**
     * Releases the lock only when it is still held with the given token.
     */
    public function unlock(string $key, string $token): void;
}
