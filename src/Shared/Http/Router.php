<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use LogicException;
use ReflectionClass;

final class Router
{
    private const string INVALID_ROUTE_METHOD_MESSAGE = 'Route methods must be public and require no arguments.';
    private const string DUPLICATE_ROUTE_MESSAGE = 'A route is already registered for this method and path.';
    private const string HEADER_FORMAT = '%s: %s';
    private const string STATUS_BODY_FORMAT = '%d %s';

    /** @var array<string, array<string, callable(): void>> */
    private array $routes = [];

    public function __construct(private readonly CsrfToken $csrfToken)
    {
    }

    public function registerController(object $controller): void
    {
        foreach ((new ReflectionClass($controller))->getMethods() as $method) {
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

    public function dispatch(string $method, string $path): void
    {
        $action = $this->routes[$method][$path] ?? null;

        if ($action === null) {
            $this->respondWithStatus(ResponseStatusEnum::NOT_FOUND);

            return;
        }

        if (
            $method === HttpMethodEnum::POST->value
            && !$this->csrfToken->isValid($_POST[CsrfToken::SESSION_KEY] ?? null)
        ) {
            $this->respondWithStatus(ResponseStatusEnum::FORBIDDEN);

            return;
        }

        $action();
    }

    private function respondWithStatus(ResponseStatusEnum $status): void
    {
        http_response_code($status->value);
        header(sprintf(self::HEADER_FORMAT, HeaderNameEnum::CONTENT_TYPE->value, ContentTypeEnum::PLAIN->value));
        echo sprintf(self::STATUS_BODY_FORMAT, $status->value, $status->message());
    }
}
