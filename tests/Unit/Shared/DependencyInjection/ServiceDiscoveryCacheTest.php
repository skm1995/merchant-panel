<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection;

use App\Shared\DependencyInjection\ServiceDiscoveryCache;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\OnlySingleStore;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\SingleStoreInterface;

final class ServiceDiscoveryCacheTest extends TestCase
{
    private const string NAMESPACE = 'Tests\\Unit\\Shared\\DependencyInjection\\Fixture\\Services';
    private const string SOURCE_FILE = 'OnlySingleStore.php';
    private const string BINDINGS_KEY = 'bindings';
    private const string DISCOVERY_KEY = 'discovery';

    private string $cacheFile;
    private string $directory;

    protected function setUp(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/service-discovery-cache-test-' . bin2hex(random_bytes(4)) . '.php';
        $this->directory = __DIR__ . '/Fixture/Services';
    }

    protected function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function testWritesTheDiscoveryOnFirstLoad(): void
    {
        $discovery = new ServiceDiscoveryCache($this->cacheFile)->load($this->directory, self::NAMESPACE);

        self::assertFileExists($this->cacheFile);
        self::assertStringStartsWith('single implementation', $discovery->describe()[SingleStoreInterface::class]);
    }

    public function testReusesTheCacheWhileTheSourceIsUnchanged(): void
    {
        $cache = new ServiceDiscoveryCache($this->cacheFile);
        $cache->load($this->directory, self::NAMESPACE);
        $this->rewriteCachedBindings([]);

        self::assertArrayNotHasKey(SingleStoreInterface::class, $cache->load($this->directory, self::NAMESPACE)->describe());
    }

    public function testRebuildsTheCacheWhenASourceFileChanges(): void
    {
        $cache = new ServiceDiscoveryCache($this->cacheFile);
        $cache->load($this->directory, self::NAMESPACE);
        $this->rewriteCachedBindings([]);
        $source = $this->directory . '/' . self::SOURCE_FILE;
        $modified = filemtime($source);
        self::assertIsInt($modified);
        touch($source, $modified + 1);

        try {
            $descriptions = $cache->load($this->directory, self::NAMESPACE)->describe();
        } finally {
            touch($source, $modified);
        }

        self::assertStringEndsWith(OnlySingleStore::class, $descriptions[SingleStoreInterface::class]);
    }

    /** @param array<string, class-string> $bindings */
    private function rewriteCachedBindings(array $bindings): void
    {
        $cached = require $this->cacheFile;
        self::assertIsArray($cached);
        self::assertIsArray($cached[self::DISCOVERY_KEY]);
        $cached[self::DISCOVERY_KEY][self::BINDINGS_KEY] = $bindings;
        file_put_contents($this->cacheFile, '<?php return ' . var_export($cached, true) . ';');

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }
}
