<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\CsrfToken;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

final class AsyncConfirmationFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'async-confirmation-';
    private const string PASSWORD = 'functional-password-123';
    private const string REGISTER_PATH = '/register';
    private const string CONFIRM_PATH = '/confirm';
    private const string CONFIRM_LINK_PATH = '/confirm?token=%s';
    private const string CSRF_FIELD_PATTERN = '/name="%s" value="([a-f0-9]+)"/';
    private const string INVALID_CONFIRMATION_MESSAGE = 'This confirmation link is invalid or has expired.';
    private const string CONFIRMATION_LINK_PATTERN = '/confirm\?token=([a-f0-9]{64})/';
    private const string MAILHOG_API_BASE_URL_ENVIRONMENT_KEY = 'MAILHOG_API_BASE_URL';
    private const string APP_BASE_URL_ENVIRONMENT_KEY = 'APP_BASE_URL';
    private const string URL_PATH_SEPARATOR = '/';
    private const string MAILHOG_MESSAGE_PATH = '/api/v1/messages/%s';
    private const string HTTP_OPTIONS_KEY = 'http';
    private const string HTTP_METHOD_KEY = 'method';
    private const string DELETE_METHOD = 'DELETE';
    private const int MESSAGE_WAIT_TIMEOUT_MICROSECONDS = 15000000;
    private const int MESSAGE_POLL_INTERVAL_MICROSECONDS = 100000;

    private string $email;
    private PDO $pdo;
    private ?string $mailhogMessageId = null;
    private ?string $sessionCookie = null;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);

        if ($this->mailhogMessageId !== null) {
            $messagePath = sprintf(self::MAILHOG_MESSAGE_PATH, rawurlencode($this->mailhogMessageId));
            $context = stream_context_create([
                self::HTTP_OPTIONS_KEY => [self::HTTP_METHOD_KEY => self::DELETE_METHOD],
            ]);
            file_get_contents(self::mailhogBaseUrl() . $messagePath, false, $context);
        }
    }

    public function testRegistrationEmailIsSentAsynchronouslyAndConfirmsPersistedUser(): void
    {
        $csrfToken = $this->request(self::REGISTER_PATH) |> self::csrfToken(...);
        $this->request(self::REGISTER_PATH, [
            'email' => $this->email,
            'password' => self::PASSWORD,
            CsrfToken::FIELD_NAME => $csrfToken,
        ]);

        $messageBody = $this->waitForMail() |> quoted_printable_decode(...);
        self::assertMatchesRegularExpression(self::CONFIRMATION_LINK_PATTERN, $messageBody);
        preg_match(self::CONFIRMATION_LINK_PATTERN, $messageBody, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);

        $encodedToken = $token |> rawurlencode(...);
        $confirmationCsrfToken = sprintf(self::CONFIRM_LINK_PATH, $encodedToken)
            |> $this->request(...)
            |> self::csrfToken(...);
        $users = new PdoUserRepository($this->pdo);
        $unconfirmedUser = $users->findByEmail($this->email);
        self::assertNotNull($unconfirmedUser);
        self::assertFalse($unconfirmedUser->isVerified());
        $confirmationForm = ['token' => $token, CsrfToken::FIELD_NAME => $confirmationCsrfToken];
        $this->request(self::CONFIRM_PATH, $confirmationForm);
        $replayResponse = $this->request(self::CONFIRM_PATH, $confirmationForm);

        $confirmedUser = $users->findByEmail($this->email);
        self::assertNotNull($confirmedUser);
        self::assertTrue($confirmedUser->isVerified());
        self::assertStringContainsString(self::INVALID_CONFIRMATION_MESSAGE, $replayResponse);
    }

    private static function csrfToken(string $page): string
    {
        preg_match(sprintf(self::CSRF_FIELD_PATTERN, CsrfToken::FIELD_NAME), $page, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);

        return $token;
    }

    /** @param array<string, string>|null $form */
    private function request(string $path, ?array $form = null): string
    {
        $httpOptions = [
            'method' => $form === null ? 'GET' : 'POST',
            'ignore_errors' => true,
            'timeout' => 5,
        ];
        if ($form !== null) {
            $headers = ['Content-Type: application/x-www-form-urlencoded'];
            if ($this->sessionCookie !== null) {
                $headers[] = 'Cookie: ' . $this->sessionCookie;
            }
            $httpOptions['header'] = implode("\r\n", $headers);
            $httpOptions['content'] = http_build_query($form);
        } elseif ($this->sessionCookie !== null) {
            $httpOptions['header'] = 'Cookie: ' . $this->sessionCookie;
        }

        $context = stream_context_create(['http' => $httpOptions]);
        $requestUrl = rtrim(Environment::get(self::APP_BASE_URL_ENVIRONMENT_KEY), self::URL_PATH_SEPARATOR) . $path;
        $response = file_get_contents($requestUrl, false, $context);
        foreach (http_get_last_response_headers() ?? [] as $header) {
            if (str_starts_with($header, 'Set-Cookie: PHPSESSID=')) {
                $this->sessionCookie = explode(';', substr($header, strlen('Set-Cookie: ')), 2)[0];
            }
        }

        self::assertIsString($response);

        return $response;
    }

    private function waitForMail(): string
    {
        $deadline = microtime(true) + (self::MESSAGE_WAIT_TIMEOUT_MICROSECONDS / 1000000);

        do {
            $url = self::mailhogBaseUrl() . '/api/v2/search?kind=to&query=' . rawurlencode($this->email);
            $response = file_get_contents($url);

            if (is_string($response)) {
                $data = json_decode($response, true);
                $message = $data['items'][0] ?? null;
                $messageId = is_array($message) ? ($message['ID'] ?? null) : null;
                $body = is_array($message) ? ($message['Content']['Body'] ?? null) : null;

                if (is_string($messageId) && is_string($body)) {
                    $this->mailhogMessageId = $messageId;

                    return $body;
                }
            }

            usleep(self::MESSAGE_POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for the confirmation email in MailHog.');
    }

    private static function mailhogBaseUrl(): string
    {
        return rtrim(Environment::get(self::MAILHOG_API_BASE_URL_ENVIRONMENT_KEY), self::URL_PATH_SEPARATOR);
    }
}
