<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ServerParameterEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Serialization\JsonDecoder;
use LogicException;

final readonly class Request
{
    public const string ROOT_PATH = '/';
    private const string API_PATH_PREFIX = '/api/';
    private const string HEADER_SERVER_KEY_PREFIX = 'HTTP_';
    private const string HEADER_WORD_SEPARATOR = '-';
    private const string SERVER_KEY_WORD_SEPARATOR = '_';
    private const string BODY_STREAM = 'php://input';
    private const string UNKNOWN_ROUTE_PARAMETER_FORMAT = 'The route declares no "%s" parameter.';
    private const string INVALID_JSON_MESSAGE = 'The request body must be a JSON object.';

    /**
     * @param array<mixed> $query
     * @param array<mixed> $post
     * @param array<string, string> $headers header values keyed by lowercase name
     * @param array<string, string> $routeParameters
     */
    public function __construct(
        public string $method,
        public string $path,
        private array $query = [],
        private array $post = [],
        private array $headers = [],
        private ?string $body = null,
        private array $routeParameters = [],
        public ?string $clientAddress = null,
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
            self::headersFromServer(),
            file_get_contents(self::BODY_STREAM) ?: null,
            clientAddress: self::stringValue($_SERVER, ServerParameterEnum::REMOTE_ADDR->value),
        );
    }

    public function isApi(): bool
    {
        return self::isApiPath($this->path);
    }

    public static function isApiPath(string $path): bool
    {
        return str_starts_with($path, self::API_PATH_PREFIX);
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
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->post,
            $this->headers,
            $this->body,
            $parameters,
            $this->clientAddress,
        );
    }

    /**
     * A parameter declared in the matched route path; asking for an undeclared one is a bug.
     */
    public function routeParameter(string $name): string
    {
        return $this->routeParameters[$name] ?? throw new LogicException(sprintf(self::UNKNOWN_ROUTE_PARAMETER_FORMAT, $name));
    }

    /** @return array<string, string> header values keyed by lowercase name */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Decodes a JSON object body.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $data = $this->body === null ? null : JsonDecoder::toArray($this->body);

        if ($data === null || array_is_list($data)) {
            throw new InvalidRequestException(self::INVALID_JSON_MESSAGE);
        }

        return $data;
    }

    /** @return array<string, string> */
    private static function headersFromServer(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, self::HEADER_SERVER_KEY_PREFIX)) {
                $name = substr($key, strlen(self::HEADER_SERVER_KEY_PREFIX))
                    |> strtolower(...)
                    |> (static fn (string $name): string => str_replace(self::SERVER_KEY_WORD_SEPARATOR, self::HEADER_WORD_SEPARATOR, $name));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /** @param array<mixed> $input */
    private static function stringValue(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
