<?php

declare(strict_types=1);

use App\Shared\Cache\RedisCacheStore;
use App\Shared\Cache\RedisConnection;
use App\Shared\Cache\StampedeProtectedCache;
use App\Shared\Clock\SystemClock;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

const COUNTER_SUFFIX = ':computations';
const VALUE = 'expensive value';
const TTL_SECONDS = 60;
const COMPUTATION_MICROSECONDS = 300000;

$key = $argv[1] ?? exit(1);
$redis = RedisConnection::createFromEnvironment();
$cache = new StampedeProtectedCache(new RedisCacheStore($redis), new SystemClock());

echo $cache->remember($key, TTL_SECONDS, static function () use ($redis, $key): string {
    $redis->incr($key . COUNTER_SUFFIX);
    usleep(COMPUTATION_MICROSECONDS);

    return VALUE;
});
