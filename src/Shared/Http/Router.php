<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Config\Environment;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final class Router
{
    private const string INVALID_ROUTE_METHOD_MESSAGE = 'Route methods must be public and accept at most a Request.';
    private const string DUPLICATE_ROUTE_MESSAGE = 'A route is already registered for this method and path.';
    private const string PARAMETER_PATTERN = '/\{(\w+)(?::((?:[^{}]|\{[^{}]*\})+))?\}/';
    private const string PARAMETER_GROUP_FORMAT = '(?P<%s>%s)';
    private const string DEFAULT_PARAMETER_PATTERN = '[^/]+';
    private const int NOT_CAPTURED_OFFSET = -1;
    private const string NOT_FOUND_DETAIL = 'No route matches this method and path.';
    private const string INVALID_CSRF_DETAIL = 'The CSRF token is missing or invalid.';
    private const string INVALID_API_KEY_DETAIL = 'A valid X-Api-Key header is required.';
    private const string API_KEY_HEADER = 'X-Api-Key';
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

                $this->routes[$key][$route->path] = $this->action($controller, $method);

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
            $this->reject($request, ResponseStatusEnum::NOT_FOUND, self::NOT_FOUND_DETAIL);

            return;
        }

        if (
            $request->method === HttpMethodEnum::POST->value
            && !$request->isApi()
            && !$this->csrfToken->isValid($request->post(CsrfToken::FIELD_NAME))
        ) {
            $this->reject($request, ResponseStatusEnum::FORBIDDEN, self::INVALID_CSRF_DETAIL);

            return;
        }

        $action($request->withRouteParameters($parameters));
    }

    private function reject(Request $request, ResponseStatusEnum $status, string $detail): void
    {
        if ($request->isApi()) {
            $this->responder->problem($status, $detail);

            return;
        }

        $this->responder->status($status);
    }

    /** @return callable(Request): void */
    private function action(object $controller, ReflectionMethod $method): callable
    {
        $mappings = array_map(
            static fn ($attribute): MapException => $attribute->newInstance(),
            $method->getAttributes(MapException::class),
        );
        $apiKey = ($method->getAttributes(RequireApiKey::class)[0] ?? null)?->newInstance();

        return function (Request $request) use ($controller, $method, $mappings, $apiKey): void {
            if ($apiKey !== null && !self::hasValidApiKey($request, $apiKey)) {
                $this->reject($request, ResponseStatusEnum::UNAUTHORIZED, self::INVALID_API_KEY_DETAIL);

                return;
            }

            try {
                $method->invoke($controller, $request);
            } catch (InvalidRequestException $exception) {
                $this->reject($request, ResponseStatusEnum::BAD_REQUEST, $exception->getMessage());
            } catch (PreconditionRequiredException $exception) {
                $this->reject($request, ResponseStatusEnum::PRECONDITION_REQUIRED, $exception->getMessage());
            } catch (Throwable $exception) {
                foreach ($mappings as $mapping) {
                    if ($exception instanceof $mapping->exception) {
                        $this->responder->problem($mapping->status, $exception->getMessage());

                        return;
                    }
                }

                throw $exception;
            }
        };
    }

    private static function hasValidApiKey(Request $request, RequireApiKey $requirement): bool
    {
        $provided = $request->header(self::API_KEY_HEADER);

        return $provided !== null && hash_equals(Environment::get($requirement->environmentKey), $provided);
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
