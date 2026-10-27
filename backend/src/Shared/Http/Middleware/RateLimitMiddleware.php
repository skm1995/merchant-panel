<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\RateLimit;
use App\Shared\Http\RateLimitPolicy;
use App\Shared\Http\RateLimiter;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;

/**
 * Answers 429 with Retry-After once a client goes over the route's limit.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    private const string UNKNOWN_CLIENT = 'unknown';
    private const string HEADER_FORMAT = '%s: %d';
    private const string DETAIL_FORMAT = 'Too many requests. Try again in %d seconds.';

    public function __construct(
        private RateLimit $rateLimit,
        private RateLimiter $limiter,
        private Responder $responder,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        $retryAfter = $this->limiter->consume(
            $this->rateLimit->environmentKey,
            RateLimitPolicy::fromEnvironment($this->rateLimit->environmentKey),
            $request->clientAddress ?? self::UNKNOWN_CLIENT,
        );

        if ($retryAfter !== null) {
            header(sprintf(self::HEADER_FORMAT, HeaderNameEnum::RETRY_AFTER->value, $retryAfter));
            $this->responder->error($request, ResponseStatusEnum::TOO_MANY_REQUESTS, sprintf(self::DETAIL_FORMAT, $retryAfter));

            return;
        }

        $next->handle($request);
    }
}
