<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

/**
 * Builds the application container from services discovered in src/. Nothing is
 * registered by hand: see ServiceDiscovery for the rules.
 */
final class ContainerFactory
{
    private const string SOURCE_DIRECTORY = '%s/src';
    private const string ROOT_NAMESPACE = 'App';
    // One cache per source tree and system user: web and CLI processes may run as different
    // users, and neither may replace the other's file in the shared temporary directory.
    private const string CACHE_FILE_FORMAT = '%s/app-services-%s-%d.php';
    private const string CACHE_KEY_ALGORITHM = 'xxh3';

    public static function create(): Container
    {
        return new Container(self::discovery()->definitions());
    }

    public static function discovery(): ServiceDiscovery
    {
        $source = sprintf(self::SOURCE_DIRECTORY, dirname(__DIR__, 3));
        $cacheFile = sprintf(self::CACHE_FILE_FORMAT, sys_get_temp_dir(), hash(self::CACHE_KEY_ALGORITHM, $source), posix_geteuid());

        return new ServiceDiscoveryCache($cacheFile)->load($source, self::ROOT_NAMESPACE);
    }
}
