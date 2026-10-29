<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\RateLimiter;
use App\Shared\Http\RateLimitPolicy;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\InMemoryCacheStore;

final class RateLimiterTest extends TestCase
{
    private const string BUCKET = 'SIGN_IN_RATE_LIMIT';
    private const string CLIENT = '192.0.2.1';
    private const string OTHER_CLIENT = '192.0.2.2';
    private const string TEN_SECONDS_INTO_A_MINUTE = '2026-10-27 12:00:10';
    private const string NEXT_MINUTE = '2026-10-27 12:01:00';
    private const string LOGGER_CHANNEL = 'test';
    private const string UNAVAILABLE_WARNING = 'Rate limiting skipped because the cache is unavailable';
    private const int LIMIT = 2;
    private const int WINDOW_SECONDS = 60;
    private const int SECONDS_LEFT_IN_WINDOW = 50;

    private InMemoryCacheStore $store;
    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->store = new InMemoryCacheStore();
        $this->logs = new TestHandler();
    }

    public function testAllowsRequestsUpToTheLimitAndThenReportsWhenTheWindowEnds(): void
    {
        $limiter = $this->limiter(self::TEN_SECONDS_INTO_A_MINUTE);

        self::assertNull($limiter->consume(self::BUCKET, $this->policy(), self::CLIENT));
        self::assertNull($limiter->consume(self::BUCKET, $this->policy(), self::CLIENT));
        self::assertSame(self::SECONDS_LEFT_IN_WINDOW, $limiter->consume(self::BUCKET, $this->policy(), self::CLIENT));
        self::assertNull($limiter->consume(self::BUCKET, $this->policy(), self::OTHER_CLIENT));
    }

    public function testStartsCountingAgainInTheNextWindow(): void
    {
        $limiter = $this->limiter(self::TEN_SECONDS_INTO_A_MINUTE);
        for ($request = 0; $request <= self::LIMIT; $request++) {
            $limiter->consume(self::BUCKET, $this->policy(), self::CLIENT);
        }

        self::assertNull($this->limiter(self::NEXT_MINUTE)->consume(self::BUCKET, $this->policy(), self::CLIENT));
    }

    public function testLetsRequestsThroughAndWarnsWhenTheCacheIsUnavailable(): void
    {
        $this->store->unavailable = true;

        self::assertNull($this->limiter(self::TEN_SECONDS_INTO_A_MINUTE)->consume(self::BUCKET, $this->policy(), self::CLIENT));
        self::assertTrue($this->logs->hasWarning(self::UNAVAILABLE_WARNING));
    }

    private function limiter(string $now): RateLimiter
    {
        return new RateLimiter($this->store, new FrozenClock($now), new Logger(self::LOGGER_CHANNEL, [$this->logs]));
    }

    private function policy(): RateLimitPolicy
    {
        return new RateLimitPolicy(self::LIMIT, self::WINDOW_SECONDS);
    }
}
