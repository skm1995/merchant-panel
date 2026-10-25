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
    private const string REGISTER_HEADING = '<h1>Create account</h1>';
    private const string LOGIN_HEADING = '<h1>Log in</h1>';
    private const string HTTP_OPTIONS_KEY = 'http';
    private const string IGNORE_ERRORS_KEY = 'ignore_errors';

    public function testApacheRoutesRegistrationPageToFrontController(): void
    {
        self::assertStringContainsString(self::REGISTER_HEADING, self::get(self::REGISTER_PATH));
    }

    public function testApacheRoutesLoginPageToFrontController(): void
    {
        self::assertStringContainsString(self::LOGIN_HEADING, self::get(self::LOGIN_PATH));
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
