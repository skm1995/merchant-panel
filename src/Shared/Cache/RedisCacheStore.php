<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use Redis;

final class RedisCacheStore implements CacheStoreInterface
{
    private const string EXPIRE_SECONDS_OPTION = 'EX';
    private const string EXPIRE_MILLISECONDS_OPTION = 'PX';
    private const string ONLY_IF_ABSENT_OPTION = 'NX';
    private const int TOKEN_BYTES = 16;
    private const int SINGLE_KEY = 1;
    private const string UNLOCK_SCRIPT = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('del', KEYS[1])
        end
        return 0
        LUA;

    public function __construct(private readonly Redis $redis)
    {
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->redis->set($key, $value, [self::EXPIRE_SECONDS_OPTION => $ttlSeconds]);
    }

    public function delete(array $keys): void
    {
        if ($keys !== []) {
            $this->redis->del($keys);
        }
    }

    public function lock(string $key, int $ttlMilliseconds): ?string
    {
        $token = random_bytes(self::TOKEN_BYTES) |> bin2hex(...);
        $acquired = $this->redis->set($key, $token, [
            self::ONLY_IF_ABSENT_OPTION,
            self::EXPIRE_MILLISECONDS_OPTION => $ttlMilliseconds,
        ]);

        return $acquired === true ? $token : null;
    }

    /**
     * Compares and deletes atomically in Redis, so a lock that expired and was taken over by
     * another process is never released by its previous owner.
     */
    public function unlock(string $key, string $token): void
    {
        $this->redis->eval(self::UNLOCK_SCRIPT, [$key, $token], self::SINGLE_KEY);
    }
}
