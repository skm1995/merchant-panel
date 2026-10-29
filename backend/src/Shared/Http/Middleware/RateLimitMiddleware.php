<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\LoadTestKey;
use App\Shared\Http\RateLimit;
use App\Shared\Http\RateLimiter;
use App\Shared\Http\RateLimitPolicy;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;

/**
 * Answers 429 with Retry-After once a client goes over the route's limit. Requests with the
 * load test key from inside the server, the Black Friday simulator's, are not counted.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    private const string UNKNOWN_CLIENT = 'unknown';
    private const string DETAIL_FORMAT = 'Too many requests. Try again in %d seconds.';

    public function __construct(
        private RateLimit $rateLimit,
        private RateLimiter $limiter,
        private Responder $responder,
        private LoadTestKey $loadTestKey,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        if ($this->loadTestKey->identifiesSimulator($request)) {
            $next->handle($request);

            return;
        }

        $retryAfter = $this->limiter->consume(
            $this->rateLimit->environmentKey,
            RateLimitPolicy::fromEnvironment($this->rateLimit->environmentKey),
            $request->clientAddress ?? self::UNKNOWN_CLIENT,
        );

        if ($retryAfter !== null) {
            $this->responder->sendHeader(HeaderNameEnum::RETRY_AFTER, (string) $retryAfter);
            $this->responder->error($request, ResponseStatusEnum::TOO_MANY_REQUESTS, sprintf(self::DETAIL_FORMAT, $retryAfter));

            return;
        }

        $next->handle($request);
    }
}
