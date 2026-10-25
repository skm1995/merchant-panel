<?php

declare(strict_types=1);

namespace App\Core;

use App\Enum\HttpMethodEnum;
use App\Enum\ContentTypeEnum;
use App\Enum\HeaderNameEnum;

final class Router
{
    private const int NOT_FOUND_STATUS_CODE = 404;
    private const string NOT_FOUND_MESSAGE = '404 Not Found';

    /** @var array<string, array<string, callable(): void>> */
    private array $routes = [];

    public function get(string $path, callable $action): void
    {
        $this->routes[HttpMethodEnum::GET->value][$path] = $action;
    }

    public function dispatch(string $method, string $path): void
    {
        $action = $this->routes[$method][$path] ?? null;

        if ($action === null) {
            http_response_code(self::NOT_FOUND_STATUS_CODE);
            header(
                HeaderNameEnum::CONTENT_TYPE->value . ': '
                . ContentTypeEnum::PLAIN->value,
            );
            echo $this->message();
            return;
        }

        $action();
    }

    public function message(): string
    {
        return self::NOT_FOUND_MESSAGE;
    }
}
