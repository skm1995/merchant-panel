<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Cache\CacheStoreInterface;
use App\Shared\Cache\Exception\CacheUnavailableException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Counts each client's requests in fixed time windows. When the cache is down, requests go
 * through unlimited: a short gap in protection is better than refusing every sign-in.
 */
final readonly class RateLimiter
{
    private const string KEY_FORMAT = 'rate-limit:%s:%s:%d';
    private const string UNAVAILABLE_WARNING = 'Rate limiting skipped because the cache is unavailable';
    private const string EXCEPTION_KEY = 'exception';

    public function __construct(
        private CacheStoreInterface $store,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Counts a request of the client against the bucket and returns the seconds until its
     * window ends when the client went over the limit, or null when the request may proceed.
     */
    public function consume(string $bucket, RateLimitPolicy $policy, string $client): ?int
    {
        $now = $this->clock->now()->getTimestamp();
        $window = intdiv($now, $policy->windowSeconds);

        try {
            $count = $this->store->increment(sprintf(self::KEY_FORMAT, $bucket, $client, $window), $policy->windowSeconds);
        } catch (CacheUnavailableException $exception) {
            $this->logger->warning(self::UNAVAILABLE_WARNING, [self::EXCEPTION_KEY => $exception]);

            return null;
        }

        return $count > $policy->limit ? ($window + 1) * $policy->windowSeconds - $now : null;
    }
}
