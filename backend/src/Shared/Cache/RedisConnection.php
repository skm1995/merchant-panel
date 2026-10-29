<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use App\Shared\Config\Environment;
use App\Shared\DependencyInjection\ServiceFactory;
use Redis;
use RedisException;

final class RedisConnection
{
    private const string HOST = 'REDIS_HOST';
    private const string PORT = 'REDIS_PORT';
    private const string DATABASE = 'REDIS_DATABASE';
    private const string FIRST_DATABASE = '0';
    private const float CONNECT_TIMEOUT_SECONDS = 0.5;
    private const float READ_TIMEOUT_SECONDS = 1.0;
    private const int NO_RETRY_INTERVAL = 0;

    /**
     * Returns a client even when Redis cannot be reached, after a short timeout: the cache
     * is optional, so a down server must neither fail nor slow every request. RedisCacheStore
     * reports such a client as unavailable when it is used.
     */
    #[ServiceFactory]
    public static function createFromEnvironment(): Redis
    {
        $redis = new Redis();

        try {
            // The error operator keeps an unresolvable host's warning out of the response.
            $connected = @$redis->connect(
                Environment::get(self::HOST),
                Environment::positiveInteger(self::PORT),
                self::CONNECT_TIMEOUT_SECONDS,
                null,
                self::NO_RETRY_INTERVAL,
                self::READ_TIMEOUT_SECONDS,
            );
        } catch (RedisException) {
            return $redis;
        }

        if ($connected) {
            $redis->select((int) (Environment::find(self::DATABASE) ?? self::FIRST_DATABASE));
        }

        return $redis;
    }
}
