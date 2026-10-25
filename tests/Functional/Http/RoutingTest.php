<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use PHPUnit\Framework\TestCase;

final class RoutingTest extends TestCase
{
    private const string BASE_URL_ENVIRONMENT_KEY = 'APP_BASE_URL';
    private const string DEFAULT_BASE_URL = 'http://localhost';
    private const string REGISTER_PATH = '/register';
    private const string LOGIN_PATH = '/login';
    private const string CONFIRMATION_SENT_PATH = '/confirmation-sent';
    private const string EMAIL_CONFIRMED_PATH = '/email-confirmed';
    private const string REGISTER_HEADING = '<h1>Create account</h1>';
    private const string LOGIN_HEADING = '<h1>Log in</h1>';
    private const string CONFIRMATION_SENT_HEADING = '<h1>Check your email</h1>';
    private const string EMAIL_CONFIRMED_HEADING = '<h1>Email confirmed</h1>';
    private const string HTTP_OPTIONS_KEY = 'http';
    private const string IGNORE_ERRORS_KEY = 'ignore_errors';
    private const string SESSION_COOKIE_HEADER = 'Set-Cookie: PHPSESSID=';

    public function testApacheRoutesRegistrationPageToFrontController(): void
    {
        $body = self::REGISTER_PATH |> self::get(...);
        self::assertStringContainsString(self::REGISTER_HEADING, $body);
    }

    public function testApacheRoutesLoginPageToFrontController(): void
    {
        $body = self::LOGIN_PATH |> self::get(...);
        self::assertStringContainsString(self::LOGIN_HEADING, $body);
    }

    public function testApacheRoutesConfirmationSentPageToFrontController(): void
    {
        $body = self::CONFIRMATION_SENT_PATH |> self::get(...);
        self::assertStringContainsString(self::CONFIRMATION_SENT_HEADING, $body);
    }

    public function testApacheRoutesEmailConfirmedPageToFrontController(): void
    {
        $body = self::EMAIL_CONFIRMED_PATH |> self::get(...);
        self::assertStringContainsString(self::EMAIL_CONFIRMED_HEADING, $body);
    }

    public function testSessionCookieIsHttpOnlyAndSameSite(): void
    {
        self::get(self::LOGIN_PATH);
        $headers = http_get_last_response_headers();
        self::assertIsArray($headers);
        $sessionCookieHeaders = array_filter(
            $headers,
            static fn (string $header): bool => str_starts_with($header, self::SESSION_COOKIE_HEADER),
        );
        $sessionCookieHeader = implode('; ', $sessionCookieHeaders);

        self::assertStringContainsString('HttpOnly', $sessionCookieHeader);
        self::assertStringContainsString('SameSite=Lax', $sessionCookieHeader);
    }

    private static function get(string $path): string
    {
        $baseUrl = getenv(self::BASE_URL_ENVIRONMENT_KEY);
        $url = (is_string($baseUrl) && strlen($baseUrl) > 0 ? $baseUrl : self::DEFAULT_BASE_URL) . $path;
        $context = stream_context_create([
            self::HTTP_OPTIONS_KEY => [self::IGNORE_ERRORS_KEY => true],
        ]);
        $body = file_get_contents($url, false, $context);

        self::assertIsString($body);

        return $body;
    }
}
