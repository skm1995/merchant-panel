<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use App\Shared\DependencyInjection\ServiceFactory;
use App\Shared\Config\Environment;
use Redis;

final class RedisConnection
{
    private const string HOST = 'REDIS_HOST';
    private const string PORT = 'REDIS_PORT';
    private const string DATABASE = 'REDIS_DATABASE';
    private const string FIRST_DATABASE = '0';

    #[ServiceFactory]
    public static function createFromEnvironment(): Redis
    {
        $redis = new Redis();
        $redis->connect(Environment::get(self::HOST), Environment::positiveInteger(self::PORT));
        $redis->select((int) (Environment::find(self::DATABASE) ?? self::FIRST_DATABASE));

        return $redis;
    }
}
