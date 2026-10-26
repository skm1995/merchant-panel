<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Request;

/**
 * Passes a request through middleware in order and then to the final handler.
 */
final readonly class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @param list<MiddlewareInterface> $middleware */
    public function __construct(private array $middleware, private RequestHandlerInterface $handler)
    {
    }

    public function handle(Request $request): void
    {
        $first = $this->middleware[0] ?? null;

        if ($first === null) {
            $this->handler->handle($request);

            return;
        }

        $first->process($request, new self(array_slice($this->middleware, 1), $this->handler));
    }
}
