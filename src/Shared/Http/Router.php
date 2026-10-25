<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use LogicException;
use ReflectionClass;

final class Router
{
    private const string INVALID_ROUTE_METHOD_MESSAGE = 'Route methods must be public and require no arguments.';
    private const string DUPLICATE_ROUTE_MESSAGE = 'A route is already registered for this method and path.';

    /** @var array<string, array<string, callable(): void>> */
    private array $routes = [];

    public function __construct(
        private readonly CsrfToken $csrfToken,
        private readonly Responder $responder,
    ) {
    }

    public function registerController(object $controller): void
    {
        foreach (new ReflectionClass($controller)->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $key = $route->method->value;

                if (!$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
                    throw new LogicException(self::INVALID_ROUTE_METHOD_MESSAGE);
                }

                if (isset($this->routes[$key][$route->path])) {
                    throw new LogicException(self::DUPLICATE_ROUTE_MESSAGE);
                }

                $this->routes[$key][$route->path] = static function () use ($controller, $method): void {
                    $method->invoke($controller);
                };
            }
        }
    }

    public function dispatch(Request $request): void
    {
        $action = $this->routes[$request->method][$request->path] ?? null;

        if ($action === null) {
            $this->responder->status(ResponseStatusEnum::NOT_FOUND);

            return;
        }

        if (
            $request->method === HttpMethodEnum::POST->value
            && !$this->csrfToken->isValid($request->post(CsrfToken::FIELD_NAME))
        ) {
            $this->responder->status(ResponseStatusEnum::FORBIDDEN);

            return;
        }

        $action();
    }
}
