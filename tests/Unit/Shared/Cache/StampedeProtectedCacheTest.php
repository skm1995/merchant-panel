<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Cache;

use App\Shared\Cache\StampedeProtectedCache;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\InMemoryCacheStore;
use Tests\Unit\Support\FrozenClock;

final class StampedeProtectedCacheTest extends TestCase
{
    private const string KEY = 'catalog:product:1';
    private const string OTHER_KEY = 'catalog:product:2';
    private const string FRESH = 'fresh value';
    private const string STALE = 'stale value';
    private const string NOW = '2026-10-25 12:00:00';
    private const int TTL = 60;
    private const float NEVER_EARLY = 1.0;
    private const float ALWAYS_EARLY = 1.0E-300;
    private const float EXPENSIVE_COMPUTATION_SECONDS = 0.5;

    private InMemoryCacheStore $store;
    private FrozenClock $clock;
    private int $computations = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryCacheStore();
        $this->clock = new FrozenClock(self::NOW);
    }

    public function testComputesAndStoresOnMissThenServesFromCache(): void
    {
        $cache = $this->cache(self::NEVER_EARLY);

        $values = [
            $cache->remember(self::KEY, self::TTL, $this->compute(...)),
            $cache->remember(self::KEY, self::TTL, $this->compute(...)),
        ];

        self::assertSame([self::FRESH, self::FRESH], $values);
        self::assertSame(1, $this->computations);
        self::assertSame(self::TTL, $this->store->ttls[self::KEY]);
        self::assertSame([], $this->store->locked, 'The recompute lock is released.');
    }

    public function testRecomputesEarlyWhenTheProbabilisticCheckFires(): void
    {
        $this->cache(self::NEVER_EARLY)->remember(self::KEY, self::TTL, function (): string {
            $this->clock->sleep(self::EXPENSIVE_COMPUTATION_SECONDS);

            return $this->compute();
        });
        $this->clock->sleep(self::TTL - 1);

        $this->cache(self::ALWAYS_EARLY)->remember(self::KEY, self::TTL, $this->compute(...));

        self::assertSame(2, $this->computations);
    }

    public function testServesTheCurrentValueWhileAnotherProcessRecomputes(): void
    {
        $this->cache(self::NEVER_EARLY)->remember(self::KEY, self::TTL, static fn (): string => self::STALE);
        $this->store->lockHeldElsewhere = true;

        $value = $this->cache(self::ALWAYS_EARLY)->remember(self::KEY, self::TTL, $this->compute(...));

        self::assertSame(self::STALE, $value);
        self::assertSame(0, $this->computations);
    }

    public function testWaitsForTheProcessHoldingTheLockOnAColdCache(): void
    {
        $this->cache(self::NEVER_EARLY)->remember(self::OTHER_KEY, self::TTL, static fn (): string => self::STALE);
        $this->store->valuesAppearingAfterRead[self::KEY] = $this->store->values[self::OTHER_KEY];
        $this->store->lockHeldElsewhere = true;

        $value = $this->cache(self::NEVER_EARLY)->remember(self::KEY, self::TTL, $this->compute(...));

        self::assertSame(self::STALE, $value);
        self::assertSame(0, $this->computations);
    }

    public function testFallsBackToComputingWithoutCachingWhenTheLockIsNeverReleased(): void
    {
        $this->store->lockHeldElsewhere = true;

        $value = $this->cache(self::NEVER_EARLY)->remember(self::KEY, self::TTL, $this->compute(...));

        self::assertSame(self::FRESH, $value);
        self::assertArrayNotHasKey(self::KEY, $this->store->values);
    }

    private function compute(): string
    {
        $this->computations++;

        return self::FRESH;
    }

    private function cache(float $random): StampedeProtectedCache
    {
        return new StampedeProtectedCache($this->store, $this->clock, static fn (): float => $random, 0);
    }
}
