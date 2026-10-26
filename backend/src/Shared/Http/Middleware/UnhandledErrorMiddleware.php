<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The last line of defense: logs an unexpected failure with its stack trace and request id,
 * and answers without revealing internals such as SQL errors.
 */
final readonly class UnhandledErrorMiddleware implements MiddlewareInterface
{
    private const string LOG_MESSAGE = 'Unhandled exception';
    private const string EXCEPTION_KEY = 'exception';
    private const string DETAIL = 'An unexpected error occurred. Quote the X-Request-Id response header when reporting it.';

    public function __construct(private LoggerInterface $logger, private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        try {
            $next->handle($request);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_MESSAGE, [self::EXCEPTION_KEY => $exception]);
            $this->responder->error($request, ResponseStatusEnum::INTERNAL_SERVER_ERROR, self::DETAIL);
        }
    }
}
