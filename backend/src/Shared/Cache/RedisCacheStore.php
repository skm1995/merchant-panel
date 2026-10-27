<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use App\Shared\Cache\Exception\CacheUnavailableException;
use Closure;
use Redis;
use RedisException;

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
        $value = $this->command(static fn (Redis $redis): mixed => $redis->get($key));

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->command(static fn (Redis $redis): mixed => $redis->set($key, $value, [self::EXPIRE_SECONDS_OPTION => $ttlSeconds]));
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $results = $this->command(static fn (Redis $redis): mixed => $redis->multi()
            ->incr($key)
            ->expire($key, $ttlSeconds, self::ONLY_IF_ABSENT_OPTION)
            ->exec());

        return is_array($results) ? (int) $results[0] : throw new CacheUnavailableException();
    }

    public function delete(array $keys): void
    {
        if ($keys !== []) {
            $this->command(static fn (Redis $redis): mixed => $redis->del($keys));
        }
    }

    public function lock(string $key, int $ttlMilliseconds): ?string
    {
        $token = random_bytes(self::TOKEN_BYTES) |> bin2hex(...);
        $acquired = $this->command(static fn (Redis $redis): mixed => $redis->set($key, $token, [
            self::ONLY_IF_ABSENT_OPTION,
            self::EXPIRE_MILLISECONDS_OPTION => $ttlMilliseconds,
        ]));

        return $acquired === true ? $token : null;
    }

    /**
     * Compares and deletes atomically in Redis, so a lock that expired and was taken over by
     * another process is never released by its previous owner.
     */
    public function unlock(string $key, string $token): void
    {
        $this->command(static fn (Redis $redis): mixed => $redis->eval(self::UNLOCK_SCRIPT, [$key, $token], self::SINGLE_KEY));
    }

    /**
     * Runs a Redis command and reports a missing connection or a failed command as an
     * unavailable cache, which callers that can work without the cache catch.
     *
     * @param Closure(Redis): mixed $command
     */
    private function command(Closure $command): mixed
    {
        if (!$this->redis->isConnected()) {
            throw new CacheUnavailableException();
        }

        try {
            return $command($this->redis);
        } catch (RedisException $exception) {
            throw new CacheUnavailableException($exception);
        }
    }
}
