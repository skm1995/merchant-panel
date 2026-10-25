<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class Router
{
    private const string INVALID_ROUTE_METHOD_MESSAGE = 'Route methods must be public and accept at most a Request.';
    private const string DUPLICATE_ROUTE_MESSAGE = 'A route is already registered for this method and path.';
    private const string PARAMETER_PATTERN = '/\{(\w+)\}/';
    private const string PARAMETER_GROUP_FORMAT = '(?P<%s>[^/]+)';
    private const string ROUTE_PATTERN_FORMAT = '#^%s$#';
    private const string PATTERN_DELIMITER = '#';
    private const string PATH_PARAMETER_MARKER = '{';

    /** @var array<string, array<string, callable(Request): void>> */
    private array $routes = [];
    /** @var array<string, array<string, string>> */
    private array $patterns = [];

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

                if (!self::isValidRouteMethod($method)) {
                    throw new LogicException(self::INVALID_ROUTE_METHOD_MESSAGE);
                }

                if (isset($this->routes[$key][$route->path])) {
                    throw new LogicException(self::DUPLICATE_ROUTE_MESSAGE);
                }

                $this->routes[$key][$route->path] = static function (Request $request) use ($controller, $method): void {
                    $method->invoke($controller, $request);
                };

                if (str_contains($route->path, self::PATH_PARAMETER_MARKER)) {
                    $this->patterns[$key][$route->path] = self::compile($route->path);
                }
            }
        }
    }

    public function dispatch(Request $request): void
    {
        [$action, $parameters] = $this->match($request) ?? [null, []];

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

        $action($request->withRouteParameters($parameters));
    }

    /** @return array{callable(Request): void, array<string, string>}|null */
    private function match(Request $request): ?array
    {
        $routes = $this->routes[$request->method] ?? [];

        if (isset($routes[$request->path])) {
            return [$routes[$request->path], []];
        }

        foreach ($this->patterns[$request->method] ?? [] as $path => $pattern) {
            if (preg_match($pattern, $request->path, $matches) === 1) {
                return [$routes[$path], array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY)];
            }
        }

        return null;
    }

    /**
     * Compiles a path such as /products/{id} into a regular expression with one named
     * group per parameter; each parameter matches a single path segment.
     */
    private static function compile(string $path): string
    {
        $parts = preg_split(self::PARAMETER_PATTERN, $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$path];
        $pattern = implode(array_map(
            static fn (string $part, int $index): string => $index % 2 === 0
                ? preg_quote($part, self::PATTERN_DELIMITER)
                : sprintf(self::PARAMETER_GROUP_FORMAT, $part),
            $parts,
            array_keys($parts),
        ));

        return sprintf(self::ROUTE_PATTERN_FORMAT, $pattern);
    }

    private static function isValidRouteMethod(ReflectionMethod $method): bool
    {
        $parameters = $method->getParameters();

        if (!$method->isPublic() || count($parameters) > 1) {
            return false;
        }

        if ($parameters === []) {
            return true;
        }

        $type = $parameters[0]->getType();

        return $type instanceof ReflectionNamedType && $type->getName() === Request::class;
    }
}
