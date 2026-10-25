<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

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
    private const string CONFIRM_PATH = '/confirm';
    private const string CSRF_EMAIL_PREFIX = 'csrf-';
    private const string PASSWORD = 'functional-password-123';
    private const string METHOD_KEY = 'method';
    private const string HEADER_KEY = 'header';
    private const string CONTENT_KEY = 'content';
    private const string POST_METHOD = 'POST';
    private const string FORM_CONTENT_TYPE = 'Content-Type: application/x-www-form-urlencoded';
    private const string STATUS_LINE_FORMAT = ' %d ';
    private const int BAD_REQUEST = 400;
    private const int FORBIDDEN = 403;

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

    public function testConfirmationPageWithoutTokenIsBadRequest(): void
    {
        self::get(self::CONFIRM_PATH);

        self::assertResponseStatus(self::BAD_REQUEST);
    }

    public function testPostWithoutCsrfTokenIsForbiddenAndCreatesNoAccount(): void
    {
        $email = TestUserData::uniqueEmail(self::CSRF_EMAIL_PREFIX);
        $pdo = DatabaseConnection::createFromEnvironment();

        try {
            self::request(self::REGISTER_PATH, [
                self::HTTP_OPTIONS_KEY => [
                    self::IGNORE_ERRORS_KEY => true,
                    self::METHOD_KEY => self::POST_METHOD,
                    self::HEADER_KEY => self::FORM_CONTENT_TYPE,
                    self::CONTENT_KEY => http_build_query(['email' => $email, 'password' => self::PASSWORD]),
                ],
            ]);

            self::assertResponseStatus(self::FORBIDDEN);
            self::assertNull(new PdoUserRepository($pdo)->findByEmail($email));
        } finally {
            TestUserData::delete($pdo, $email);
        }
    }

    private static function get(string $path): string
    {
        return self::request($path, [self::HTTP_OPTIONS_KEY => [self::IGNORE_ERRORS_KEY => true]]);
    }

    /** @param array<string, array<string, mixed>> $options */
    private static function request(string $path, array $options): string
    {
        $baseUrl = getenv(self::BASE_URL_ENVIRONMENT_KEY);
        $url = (is_string($baseUrl) && strlen($baseUrl) > 0 ? $baseUrl : self::DEFAULT_BASE_URL) . $path;
        $body = file_get_contents($url, false, stream_context_create($options));

        self::assertIsString($body);

        return $body;
    }

    private static function assertResponseStatus(int $status): void
    {
        $statusLine = http_get_last_response_headers()[0] ?? null;

        self::assertIsString($statusLine);
        self::assertStringContainsString(sprintf(self::STATUS_LINE_FORMAT, $status), $statusLine);
    }
}
