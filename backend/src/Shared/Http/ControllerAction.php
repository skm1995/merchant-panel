<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Middleware\RequestHandlerInterface;
use ReflectionMethod;

/**
 * The controller method a route points to, as the last handler of its middleware pipeline.
 */
final readonly class ControllerAction implements RequestHandlerInterface
{
    public function __construct(private object $controller, private ReflectionMethod $method)
    {
    }

    public function handle(Request $request): void
    {
        $this->method->invoke($this->controller, $request);
    }
}
