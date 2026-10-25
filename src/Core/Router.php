<?php

declare(strict_types=1);

namespace App\Core;

use App\Enum\HttpMethodEnum;
use App\Enum\ContentTypeEnum;
use App\Enum\HeaderNameEnum;
use App\Enum\ResponseStatusEnum;

final class Router
{
    /** @var array<string, array<string, callable(): void>> */
    private array $routes = [];

    public function get(string $path, callable $action): void
    {
        $this->routes[HttpMethodEnum::GET->value][$path] = $action;
    }

    public function post(string $path, callable $action): void
    {
        $this->routes[HttpMethodEnum::POST->value][$path] = $action;
    }

    public function dispatch(string $method, string $path): void
    {
        $action = $this->routes[$method][$path] ?? null;

        if ($action === null) {
            http_response_code(ResponseStatusEnum::NOT_FOUND->value);
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
        $statusMessage = ResponseStatusEnum::NOT_FOUND->message();

        return sprintf('%d %s', ResponseStatusEnum::NOT_FOUND->value, $statusMessage);
    }
}
