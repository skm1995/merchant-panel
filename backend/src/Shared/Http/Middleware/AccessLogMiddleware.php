<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Request;
use Psr\Log\LoggerInterface;

final readonly class AccessLogMiddleware implements MiddlewareInterface
{
    private const string MESSAGE = 'Request handled';
    private const string METHOD_KEY = 'method';
    private const string PATH_KEY = 'path';
    private const string STATUS_KEY = 'status';
    private const string DURATION_KEY = 'duration_ms';
    private const int NANOSECONDS_PER_MILLISECOND = 1000000;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        $startedAt = hrtime(true);

        try {
            $next->handle($request);
        } finally {
            $this->logger->info(self::MESSAGE, [
                self::METHOD_KEY => $request->method,
                self::PATH_KEY => $request->path,
                self::STATUS_KEY => http_response_code(),
                self::DURATION_KEY => intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MILLISECOND),
            ]);
        }
    }
}
