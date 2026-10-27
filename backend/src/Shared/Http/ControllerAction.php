<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Middleware\RequestHandlerInterface;
use Closure;
use ReflectionMethod;

/**
 * The controller method a route points to, as the last handler of its middleware pipeline.
 */
final readonly class ControllerAction implements RequestHandlerInterface
{
    /** @param Closure(): object $resolve builds or returns the controller */
    public function __construct(private Closure $resolve, private ReflectionMethod $method)
    {
    }

    public function handle(Request $request): void
    {
        $this->method->invoke(($this->resolve)(), $request);
    }
}
