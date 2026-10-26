<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\ApiKeyMiddleware;
use App\Shared\Http\Middleware\CsrfMiddleware;
use App\Shared\Http\Middleware\ErrorMiddleware;
use App\Shared\Http\Middleware\ExceptionMappingMiddleware;
use App\Shared\Http\Middleware\MiddlewarePipeline;
use App\Shared\Http\Middleware\RequestHandlerInterface;
use App\Shared\Http\Middleware\SessionMiddleware;
use App\Shared\Http\Middleware\SignedInMiddleware;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class Router
{
    private const string INVALID_ROUTE_METHOD_MESSAGE = 'Route methods must be public and accept at most a Request.';
    private const string DUPLICATE_ROUTE_MESSAGE = 'A route is already registered for this method and path.';
    private const string PARAMETER_PATTERN = '/\{(\w+)(?::((?:[^{}]|\{[^{}]*\})+))?\}/';
    private const string PARAMETER_GROUP_FORMAT = '(?P<%s>%s)';
    private const string DEFAULT_PARAMETER_PATTERN = '[^/]+';
    private const int NOT_CAPTURED_OFFSET = -1;
    private const string NOT_FOUND_DETAIL = 'No route matches this method and path.';
    private const string ROUTE_PATTERN_FORMAT = '#^%s$#';
    private const string PATTERN_DELIMITER = '#';
    private const string PATH_PARAMETER_MARKER = '{';

    /** @var array<string, array<string, RequestHandlerInterface>> */
    private array $routes = [];
    /** @var array<string, array<string, string>> */
    private array $patterns = [];

    public function __construct(
        private readonly CsrfToken $csrfToken,
        private readonly Session $session,
        private readonly Responder $responder,
        private readonly SessionUserInterface $user,
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

                $this->routes[$key][$route->path] = $this->pipeline($route, $controller, $method);

                if (str_contains($route->path, self::PATH_PARAMETER_MARKER)) {
                    $this->patterns[$key][$route->path] = self::compile($route->path);
                }
            }
        }
    }

    public function dispatch(Request $request): void
    {
        [$handler, $parameters] = $this->match($request) ?? [null, []];

        if ($handler === null) {
            $this->responder->error($request, ResponseStatusEnum::NOT_FOUND, self::NOT_FOUND_DETAIL);

            return;
        }

        $handler->handle($request->withRouteParameters($parameters));
    }

    /**
     * Builds the policies a route needs from its path and attributes. Browser routes and API
     * routes marked #[UsesSession] or #[RequireSignedIn] get the session and CSRF protection;
     * other API routes stay sessionless.
     */
    private function pipeline(Route $route, object $controller, ReflectionMethod $method): RequestHandlerInterface
    {
        $middleware = [new ErrorMiddleware($this->responder)];

        $requiresSignIn = $method->getAttributes(RequireSignedIn::class) !== [];

        if (!Request::isApiPath($route->path) || $requiresSignIn || $method->getAttributes(UsesSession::class) !== []) {
            $middleware[] = new SessionMiddleware($this->session);
            $middleware[] = new CsrfMiddleware($this->csrfToken, $this->responder);
        }

        if ($requiresSignIn) {
            $middleware[] = new SignedInMiddleware($this->user, $this->responder);
        }

        foreach ($method->getAttributes(RequireApiKey::class) as $attribute) {
            $middleware[] = new ApiKeyMiddleware($attribute->newInstance(), $this->responder);
        }

        $mappings = array_map(
            static fn ($attribute): MapException => $attribute->newInstance(),
            $method->getAttributes(MapException::class),
        );

        if ($mappings !== []) {
            $middleware[] = new ExceptionMappingMiddleware($mappings, $this->responder);
        }

        return new MiddlewarePipeline($middleware, new ControllerAction($controller, $method));
    }

    /** @return array{RequestHandlerInterface, array<string, string>}|null */
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
     * Compiles a path such as /products/{id:\d+} into a regular expression with one named
     * group per parameter; a parameter without a constraint matches a single path segment.
     * Constraints may contain quantifiers such as {4}, but no deeper brace nesting.
     */
    private static function compile(string $path): string
    {
        preg_match_all(self::PARAMETER_PATTERN, $path, $parameters, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $parts = [];
        $offset = 0;

        foreach ($parameters as $parameter) {
            [$placeholder, $placeholderOffset] = $parameter[0];
            $hasConstraint = isset($parameter[2]) && $parameter[2][1] !== self::NOT_CAPTURED_OFFSET;
            $parts[] = preg_quote(substr($path, $offset, $placeholderOffset - $offset), self::PATTERN_DELIMITER);
            $parts[] = sprintf(
                self::PARAMETER_GROUP_FORMAT,
                $parameter[1][0],
                $hasConstraint ? $parameter[2][0] : self::DEFAULT_PARAMETER_PATTERN,
            );
            $offset = $placeholderOffset + strlen($placeholder);
        }

        $parts[] = preg_quote(substr($path, $offset), self::PATTERN_DELIMITER);

        return sprintf(self::ROUTE_PATTERN_FORMAT, implode($parts));
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
