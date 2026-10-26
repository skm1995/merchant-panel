<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\MapException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use Throwable;

/**
 * Answers the domain exceptions a route declares with #[MapException] as problem details.
 */
final readonly class ExceptionMappingMiddleware implements MiddlewareInterface
{
    /** @param list<MapException> $mappings */
    public function __construct(private array $mappings, private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        try {
            $next->handle($request);
        } catch (Throwable $exception) {
            foreach ($this->mappings as $mapping) {
                if ($exception instanceof $mapping->exception) {
                    $this->responder->problem($mapping->status, $exception->getMessage());

                    return;
                }
            }

            throw $exception;
        }
    }
}
