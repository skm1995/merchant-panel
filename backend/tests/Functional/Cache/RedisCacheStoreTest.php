<?php

declare(strict_types=1);

namespace Tests\Functional\Cache;

use App\Shared\Cache\Exception\CacheUnavailableException;
use App\Shared\Cache\RedisCacheStore;
use App\Shared\Cache\RedisConnection;
use App\Shared\Config\Environment;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedisCacheStoreTest extends TestCase
{
    private const string KEY_PREFIX = 'test:store:';
    private const string VALUE = 'cached';
    private const string FOREIGN_TOKEN = 'someone-else';
    private const int TTL = 30;
    private const int LOCK_TTL = 5000;
    private const string UNREACHABLE_HOST = 'redis-that-does-not-exist';
    private const string UNREACHABLE_PORT = '6379';
    private const string HOST_VARIABLE = 'REDIS_HOST';
    private const string PORT_VARIABLE = 'REDIS_PORT';
    private const string ASSIGNMENT_FORMAT = '%s=%s';

    private RedisCacheStore $store;
    private string $key;
    private string $host;
    private string $port;

    protected function setUp(): void
    {
        $this->store = new RedisCacheStore(RedisConnection::createFromEnvironment());
        $this->key = uniqid(self::KEY_PREFIX, true);
        $this->host = Environment::get(self::HOST_VARIABLE);
        $this->port = Environment::get(self::PORT_VARIABLE);
    }

    protected function tearDown(): void
    {
        $this->store->delete([$this->key, $this->lockKey()]);
    }

    public function testStoresReadsAndDeletesValuesWithExpiry(): void
    {
        $this->store->set($this->key, self::VALUE, self::TTL);

        self::assertSame(self::VALUE, $this->store->get($this->key));
        $ttl = RedisConnection::createFromEnvironment()->ttl($this->key);
        self::assertGreaterThan(0, $ttl);
        self::assertLessThanOrEqual(self::TTL, $ttl);

        $this->store->delete([$this->key]);

        self::assertNull($this->store->get($this->key));
    }

    public function testLockIsExclusiveAndOnlyItsOwnerCanRelease(): void
    {
        $token = $this->store->lock($this->lockKey(), self::LOCK_TTL);

        self::assertNotNull($token);
        self::assertNull($this->store->lock($this->lockKey(), self::LOCK_TTL));

        $this->store->unlock($this->lockKey(), self::FOREIGN_TOKEN);
        self::assertNull($this->store->lock($this->lockKey(), self::LOCK_TTL), 'A foreign token must not release the lock.');

        $this->store->unlock($this->lockKey(), $token);
        self::assertNotNull($this->store->lock($this->lockKey(), self::LOCK_TTL));
    }

    private function lockKey(): string
    {
        return $this->key . ':lock';
    }

    public function testCountsUpWithAnExpiryThatTheFirstIncrementSets(): void
    {
        self::assertSame(1, $this->store->increment($this->key, self::TTL));
        $firstTtl = RedisConnection::createFromEnvironment()->ttl($this->key);
        self::assertSame(2, $this->store->increment($this->key, self::TTL * 2));

        self::assertGreaterThan(0, $firstTtl);
        self::assertLessThanOrEqual(self::TTL, RedisConnection::createFromEnvironment()->ttl($this->key));
    }

    /** @return array<string, array{Closure(RedisCacheStore, string): void}> */
    public static function operations(): array
    {
        return [
            'get' => [static function (RedisCacheStore $store, string $key): void {
                $store->get($key);
            }],
            'set' => [static function (RedisCacheStore $store, string $key): void {
                $store->set($key, self::VALUE, self::TTL);
            }],
            'delete' => [static function (RedisCacheStore $store, string $key): void {
                $store->delete([$key]);
            }],
            'increment' => [static function (RedisCacheStore $store, string $key): void {
                $store->increment($key, self::TTL);
            }],
            'lock' => [static function (RedisCacheStore $store, string $key): void {
                $store->lock($key, self::LOCK_TTL);
            }],
            'unlock' => [static function (RedisCacheStore $store, string $key): void {
                $store->unlock($key, self::FOREIGN_TOKEN);
            }],
        ];
    }

    /** @param Closure(RedisCacheStore, string): void $operation */
    #[DataProvider('operations')]
    public function testReportsEveryOperationOnAnUnreachableServerAsUnavailable(Closure $operation): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::HOST_VARIABLE, self::UNREACHABLE_HOST));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::PORT_VARIABLE, self::UNREACHABLE_PORT));

        try {
            $store = new RedisCacheStore(RedisConnection::createFromEnvironment());
        } finally {
            putenv(sprintf(self::ASSIGNMENT_FORMAT, self::HOST_VARIABLE, $this->host));
            putenv(sprintf(self::ASSIGNMENT_FORMAT, self::PORT_VARIABLE, $this->port));
        }

        $this->expectException(CacheUnavailableException::class);
        $operation($store, $this->key);
    }
}
