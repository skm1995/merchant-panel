<?php

declare(strict_types=1);

namespace Tests\Functional\Cache;

use App\Shared\Cache\Exception\CacheUnavailableException;
use App\Shared\Cache\RedisCacheStore;
use App\Shared\Cache\RedisConnection;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

final class RedisCacheStoreTest extends TestCase
{
    private const string KEY_PREFIX = 'test:store:';
    private const string VALUE = 'cached';
    private const string FOREIGN_TOKEN = 'someone-else';
    private const int TTL = 30;
    private const int LOCK_TTL = 5000;
    private const string UNREACHABLE_HOST = '127.0.0.1';
    private const int UNREACHABLE_PORT = 1;

    private RedisCacheStore $store;
    private string $key;

    protected function setUp(): void
    {
        $this->store = new RedisCacheStore(RedisConnection::createFromEnvironment());
        $this->key = uniqid(self::KEY_PREFIX, true);
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

    public function testReportsAnUnreachableRedisAsUnavailable(): void
    {
        $redis = new Redis();

        try {
            $redis->connect(self::UNREACHABLE_HOST, self::UNREACHABLE_PORT);
        } catch (RedisException) {
            // An unreachable server is exactly what this test needs.
        }

        $this->expectException(CacheUnavailableException::class);
        new RedisCacheStore($redis)->increment($this->key, self::TTL);
    }
}
