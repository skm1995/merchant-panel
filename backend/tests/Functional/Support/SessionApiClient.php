<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Shared\Http\Enum\HttpMethodEnum;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\HttpBrowser;

/**
 * Calls session routes of the API like the React panel does: it keeps the session cookie
 * and sends the session's CSRF token in the X-CSRF-Token header.
 */
final class SessionApiClient
{
    private const string SESSION_PATH = '/api/v1/session';
    private const string CSRF_TOKEN_KEY = 'csrf_token';
    private const string JSON_CONTENT_TYPE = 'application/json';
    private const string CONTENT_TYPE_SERVER_KEY = 'CONTENT_TYPE';
    private const string CSRF_TOKEN_SERVER_KEY = 'HTTP_X_CSRF_TOKEN';
    private const string HEADER_SERVER_KEY_PREFIX = 'HTTP_';
    private const string HEADER_WORD_SEPARATOR = '-';
    private const string SERVER_WORD_SEPARATOR = '_';

    private HttpBrowser $browser;

    public function __construct()
    {
        $this->browser = Browser::create();
    }

    public function get(string $path): ResponseInterface
    {
        return $this->request(HttpMethodEnum::GET, $path, null, []);
    }

    /** @param array<string, string> $headers extra headers by name, such as If-Match */
    public function send(HttpMethodEnum $method, string $path, ?string $body = null, array $headers = []): ResponseInterface
    {
        $server = [self::CSRF_TOKEN_SERVER_KEY => $this->csrfToken()];

        foreach ($headers as $name => $value) {
            $server[self::HEADER_SERVER_KEY_PREFIX . strtoupper(str_replace(self::HEADER_WORD_SEPARATOR, self::SERVER_WORD_SEPARATOR, $name))] = $value;
        }

        return $this->request($method, $path, $body, $server);
    }

    public function sendWithoutCsrfToken(HttpMethodEnum $method, string $path, ?string $body = null): ResponseInterface
    {
        return $this->request($method, $path, $body, []);
    }

    private function csrfToken(): string
    {
        $token = ApiClient::json($this->get(self::SESSION_PATH))[self::CSRF_TOKEN_KEY] ?? null;
        Assert::assertIsString($token);

        return $token;
    }

    /** @param array<string, string> $server */
    private function request(HttpMethodEnum $method, string $path, ?string $body, array $server): ResponseInterface
    {
        $this->browser->request(
            $method->value,
            Browser::url($path),
            [],
            [],
            [self::CONTENT_TYPE_SERVER_KEY => self::JSON_CONTENT_TYPE, ...$server],
            $body,
        );
        $response = $this->browser->getInternalResponse();

        return new Response($response->getStatusCode(), $response->getHeaders(), $response->getContent());
    }
}
