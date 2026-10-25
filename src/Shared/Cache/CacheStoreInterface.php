<?php

declare(strict_types=1);

namespace App\Shared\Cache;

interface CacheStoreInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttlSeconds): void;

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
