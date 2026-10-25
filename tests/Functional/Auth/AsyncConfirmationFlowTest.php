<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Core\DatabaseConnection;
use App\Repository\PdoUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AsyncConfirmationFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'async-confirmation-';
    private const string EMAIL_DOMAIN = '@example.com';
    private const string PASSWORD = 'functional-password-123';
    private const string REGISTER_PATH = '/register';
    private const string CONFIRM_PATH = '/confirm?token=%s';
    private const string CONFIRMATION_LINK_PATTERN = '/confirm\?token=([a-f0-9]{64})/';
    private const string TOKEN_DELETE_QUERY = 'DELETE FROM email_verification_tokens WHERE email = :email';
    private const string USER_DELETE_QUERY = 'DELETE FROM users WHERE email = :email';
    private const string MAILHOG_API_BASE_URL_ENVIRONMENT_KEY = 'MAILHOG_API_BASE_URL';
    private const string MAILHOG_MESSAGE_PATH = '/api/v1/messages/%s';
    private const string HTTP_OPTIONS_KEY = 'http';
    private const string HTTP_METHOD_KEY = 'method';
    private const string DELETE_METHOD = 'DELETE';
    private const int MESSAGE_WAIT_TIMEOUT_MICROSECONDS = 15000000;
    private const int MESSAGE_POLL_INTERVAL_MICROSECONDS = 100000;

    private string $email;
    private PDO $pdo;
    private ?string $mailhogMessageId = null;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $randomSuffix = random_bytes(8) |> bin2hex(...);
        $this->email = self::EMAIL_PREFIX . $randomSuffix . self::EMAIL_DOMAIN;
    }

    protected function tearDown(): void
    {
        foreach ([self::TOKEN_DELETE_QUERY, self::USER_DELETE_QUERY] as $query) {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['email' => $this->email]);
        }

        if ($this->mailhogMessageId !== null) {
            $mailhogUrl = getenv(self::MAILHOG_API_BASE_URL_ENVIRONMENT_KEY);

            if (is_string($mailhogUrl)) {
                $messagePath = sprintf(self::MAILHOG_MESSAGE_PATH, rawurlencode($this->mailhogMessageId));
                $deleteUrl = rtrim($mailhogUrl, '/') . $messagePath;
                $context = stream_context_create([
                    self::HTTP_OPTIONS_KEY => [self::HTTP_METHOD_KEY => self::DELETE_METHOD],
                ]);
                file_get_contents($deleteUrl, false, $context);
            }
        }
    }

    public function testRegistrationEmailIsSentAsynchronouslyAndConfirmsPersistedUser(): void
    {
        $this->request(self::REGISTER_PATH, [
            'email' => $this->email,
            'password' => self::PASSWORD,
        ]);

        $messageBody = $this->waitForMail() |> quoted_printable_decode(...);
        self::assertMatchesRegularExpression(self::CONFIRMATION_LINK_PATTERN, $messageBody);
        preg_match(self::CONFIRMATION_LINK_PATTERN, $messageBody, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);

        $encodedToken = $token |> rawurlencode(...);
        $confirmationPath = sprintf(self::CONFIRM_PATH, $encodedToken);
        $this->request($confirmationPath);

        $users = new PdoUserRepository($this->pdo);
        $user = $users->findByEmail($this->email);
        self::assertNotNull($user);
        self::assertNotNull($user->emailVerifiedAt);
    }

    /** @param array<string, string>|null $form */
    private function request(string $path, ?array $form = null): string
    {
        $baseUrl = getenv('APP_BASE_URL');
        self::assertIsString($baseUrl);
        $httpOptions = [
            'method' => $form === null ? 'GET' : 'POST',
            'ignore_errors' => true,
            'timeout' => 5,
        ];
        if ($form !== null) {
            $httpOptions['header'] = 'Content-Type: application/x-www-form-urlencoded';
            $httpOptions['content'] = http_build_query($form);
        }

        $context = stream_context_create(['http' => $httpOptions]);
        $applicationBaseUrl = $baseUrl |> (static fn (string $url): string => rtrim($url, '/'));
        $requestUrl = $applicationBaseUrl . $path;
        $response = file_get_contents($requestUrl, false, $context);

        self::assertIsString($response);

        return $response;
    }

    private function waitForMail(): string
    {
        $mailhogUrl = getenv(self::MAILHOG_API_BASE_URL_ENVIRONMENT_KEY);
        self::assertIsString($mailhogUrl);
        $deadline = microtime(true) + (self::MESSAGE_WAIT_TIMEOUT_MICROSECONDS / 1000000);

        do {
            $mailhogBaseUrl = $mailhogUrl |> (static fn (string $url): string => rtrim($url, '/'));
            $encodedEmail = $this->email |> rawurlencode(...);
            $url = $mailhogBaseUrl . '/api/v2/search?kind=to&query=' . $encodedEmail;
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
}
