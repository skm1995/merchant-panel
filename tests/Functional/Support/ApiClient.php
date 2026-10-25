<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Shared\Http\Enum\HttpMethodEnum;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Calls the JSON API through the web server and validates responses against openapi.yaml.
 */
final class ApiClient
{
    private const string SPECIFICATION_PATH = '/openapi.yaml';

    private static ?ResponseValidator $validator = null;

    /** @param array<string, string> $query */
    public static function get(string $path, array $query = []): ResponseInterface
    {
        return self::request(HttpMethodEnum::GET, $path, ['query' => $query]);
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function send(
        HttpMethodEnum $method,
        string $path,
        ?array $json = null,
        array $headers = [],
    ): ResponseInterface {
        return self::request($method, $path, array_filter(['json' => $json, 'headers' => $headers]));
    }

    /** @param array<string, mixed> $options */
    private static function request(HttpMethodEnum $method, string $path, array $options): ResponseInterface
    {
        $response = HttpClient::create()->request($method->value, Browser::url($path), $options);

        return new Response($response->getStatusCode(), $response->getHeaders(false), $response->getContent(false));
    }

    /**
     * Fails when the response does not match the documented operation.
     */
    public static function assertMatchesSpecification(
        string $specificationPath,
        string $method,
        ResponseInterface $response,
    ): void {
        self::validator()->validate(new OperationAddress($specificationPath, strtolower($method)), $response);
    }

    /** @return array<string, mixed> */
    public static function json(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        return is_array($body) ? $body : [];
    }

    private static function validator(): ResponseValidator
    {
        return self::$validator ??= new ValidatorBuilder()
            ->fromYamlFile(dirname(__DIR__, 3) . self::SPECIFICATION_PATH)
            ->getResponseValidator();
    }
}
