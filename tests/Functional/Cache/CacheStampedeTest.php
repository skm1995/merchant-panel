<?php

declare(strict_types=1);

namespace Tests\Functional\Cache;

use App\Shared\Cache\RedisConnection;
use PHPUnit\Framework\TestCase;

/**
 * Starts many PHP processes that request the same cold key at once; the expensive
 * computation, counted in Redis, must run only once.
 */
final class CacheStampedeTest extends TestCase
{
    private const string WORKER_SCRIPT = '/stampede-reader.php';
    private const string KEY_PREFIX = 'test:stampede:';
    private const string COUNTER_SUFFIX = ':computations';
    private const string COMMAND_FORMAT = '%s %s %s';
    private const string EXPECTED_VALUE = 'expensive value';
    private const int PROCESSES = 20;

    public function testOnlyOneOfManyConcurrentReadersComputesAColdKey(): void
    {
        $key = uniqid(self::KEY_PREFIX, true);
        $redis = RedisConnection::createFromEnvironment();
        $processes = [];

        try {
            for ($process = 0; $process < self::PROCESSES; $process++) {
                $command = sprintf(self::COMMAND_FORMAT, PHP_BINARY, escapeshellarg(__DIR__ . self::WORKER_SCRIPT), escapeshellarg($key));
                $handle = popen($command, 'r');
                self::assertIsResource($handle);
                $processes[] = $handle;
            }

            $outputs = array_map(static function ($handle): string {
                $output = stream_get_contents($handle);
                pclose($handle);

                return trim((string) $output);
            }, $processes);

            self::assertSame(array_fill(0, self::PROCESSES, self::EXPECTED_VALUE), $outputs);
            self::assertSame('1', $redis->get($key . self::COUNTER_SUFFIX));
        } finally {
            $redis->del($key, $key . self::COUNTER_SUFFIX);
        }
    }
}
