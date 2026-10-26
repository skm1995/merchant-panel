<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Keeps the result of service discovery in a PHP file that OPcache serves from memory, so
 * requests skip loading and reflecting every class. The cache is rebuilt whenever a file
 * under the scanned directory is added, removed, renamed, or modified.
 */
final class ServiceDiscoveryCache
{
    private const string FINGERPRINT_KEY = 'fingerprint';
    private const string DISCOVERY_KEY = 'discovery';
    private const string FINGERPRINT_ALGORITHM = 'xxh128';
    private const string FILE_STAMP_FORMAT = '%s:%d;';
    private const string CACHE_FILE_FORMAT = "<?php\n\nreturn %s;\n";
    private const string TEMPORARY_PREFIX = 'services';

    public function __construct(private readonly string $cacheFile)
    {
    }

    public function load(string $directory, string $namespace): ServiceDiscovery
    {
        $fingerprint = self::fingerprint($directory);
        $cached = is_file($this->cacheFile) ? require $this->cacheFile : null;

        if (is_array($cached) && ($cached[self::FINGERPRINT_KEY] ?? null) === $fingerprint) {
            /** @var array{discovery: array{bindings: array<string, class-string>, defaults: array<string, class-string>, ambiguous: array<string, list<class-string>>, factories: array<string, array{class-string, string, bool}>}} $cached */
            return ServiceDiscovery::fromArray($cached[self::DISCOVERY_KEY]);
        }

        self::forgetCompiledSources($directory);
        $discovery = ServiceDiscovery::fromClasses(ClassFinder::find($directory, $namespace));
        $this->write([self::FINGERPRINT_KEY => $fingerprint, self::DISCOVERY_KEY => $discovery->toArray()]);

        return $discovery;
    }

    /**
     * OPcache may still serve the previous version of a file that just changed, so discovery
     * would reflect stale classes and cache them under the new fingerprint. Rebuilding is rare
     * outside development, so invalidating every scanned file is cheap.
     */
    private static function forgetCompiledSources(string $directory): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }

        foreach (self::files($directory) as $file) {
            opcache_invalidate($file->getPathname(), true);
        }
    }

    /** @return iterable<SplFileInfo> */
    private static function files(string $directory): iterable
    {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        return $files;
    }

    private static function fingerprint(string $directory): string
    {
        $stamps = [];

        foreach (self::files($directory) as $file) {
            $stamps[] = sprintf(self::FILE_STAMP_FORMAT, $file->getPathname(), $file->getMTime());
        }

        sort($stamps);

        return hash(self::FINGERPRINT_ALGORITHM, implode($stamps));
    }

    /**
     * Writes through a temporary file and a rename, so concurrent requests never read a
     * partially written cache. A failed write only means the next request scans again.
     *
     * @param array<string, mixed> $data
     */
    private function write(array $data): void
    {
        $temporary = tempnam(dirname($this->cacheFile), self::TEMPORARY_PREFIX);

        if ($temporary === false || file_put_contents($temporary, sprintf(self::CACHE_FILE_FORMAT, var_export($data, true))) === false) {
            return;
        }

        rename($temporary, $this->cacheFile);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }
}
