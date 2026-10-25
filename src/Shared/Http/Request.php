<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ServerParameterEnum;

final readonly class Request
{
    public const string ROOT_PATH = '/';

    /**
     * @param array<mixed> $query
     * @param array<mixed> $post
     * @param array<string, string> $routeParameters
     */
    public function __construct(
        public string $method,
        public string $path,
        private array $query = [],
        private array $post = [],
        private array $routeParameters = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER[ServerParameterEnum::REQUEST_METHOD->value] ?? null;
        $uri = $_SERVER[ServerParameterEnum::REQUEST_URI->value] ?? null;
        $path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : null;

        return new self(
            is_string($method) ? $method : HttpMethodEnum::GET->value,
            is_string($path) ? $path : self::ROOT_PATH,
            $_GET,
            $_POST,
        );
    }

    public function query(string $key): ?string
    {
        return self::stringValue($this->query, $key);
    }

    public function post(string $key): ?string
    {
        return self::stringValue($this->post, $key);
    }

    /** @param array<string, string> $parameters */
    public function withRouteParameters(array $parameters): self
    {
        return new self($this->method, $this->path, $this->query, $this->post, $parameters);
    }

    public function routeParameter(string $name): ?string
    {
        return $this->routeParameters[$name] ?? null;
    }

    /** @param array<mixed> $input */
    private static function stringValue(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
