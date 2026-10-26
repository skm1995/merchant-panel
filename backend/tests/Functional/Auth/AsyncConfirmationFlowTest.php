<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Functional\Support\Browser;
use Tests\Functional\Support\SessionApiClient;
use Tests\Functional\Support\TestUserData;

final class AsyncConfirmationFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'async-confirmation-';
    private const string PASSWORD = 'functional-password-123';
    private const string CONFIRMATION_LINK_PATTERN = '#%s\?token=([a-f0-9]{64})#';
    private const string REGISTRATIONS_PATH = '/api/v1/registrations';
    private const string CONFIRMATIONS_PATH = '/api/v1/email-confirmations';
    private const string CREDENTIALS_FORMAT = '{"email":"%s","password":"%s"}';
    private const string TOKEN_BODY_FORMAT = '{"token":"%s"}';
    private const string CONFIRM_PATH = '/confirm';
    private const string MAILPIT_API_BASE_URL_ENVIRONMENT_KEY = 'MAILPIT_API_BASE_URL';
    private const string MAILPIT_SEARCH_PATH = '/api/v1/search';
    private const string MAILPIT_SEARCH_QUERY_FORMAT = 'to:"%s"';
    private const string MAILPIT_MESSAGE_PATH = '/api/v1/message/%s';
    private const string MAILPIT_MESSAGES_PATH = '/api/v1/messages';
    private const string URL_PATH_SEPARATOR = '/';
    private const string DELETE_METHOD = 'DELETE';
    private const int MESSAGE_WAIT_TIMEOUT_SECONDS = 15;
    private const int MESSAGE_POLL_INTERVAL_MICROSECONDS = 100000;

    private string $email;
    private PDO $pdo;
    private SessionApiClient $client;
    private HttpClientInterface $mailpit;
    private ?string $mailpitMessageId = null;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
        $this->client = new SessionApiClient();
        $this->mailpit = HttpClient::createForBaseUri(rtrim(
            Environment::get(self::MAILPIT_API_BASE_URL_ENVIRONMENT_KEY),
            self::URL_PATH_SEPARATOR,
        ));
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);

        if ($this->mailpitMessageId !== null) {
            $this->mailpit->request(self::DELETE_METHOD, self::MAILPIT_MESSAGES_PATH, [
                'json' => ['IDs' => [$this->mailpitMessageId]],
            ])->getStatusCode();
        }
    }

    public function testRegistrationEmailIsSentAsynchronouslyAndConfirmsPersistedUser(): void
    {
        $registration = $this->client->send(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, sprintf(self::CREDENTIALS_FORMAT, $this->email, self::PASSWORD));
        self::assertSame(ResponseStatusEnum::ACCEPTED->value, $registration->getStatusCode());

        $linkPattern = sprintf(self::CONFIRMATION_LINK_PATTERN, preg_quote(Browser::url(self::CONFIRM_PATH), '#'));
        $messageBody = $this->waitForMail();
        self::assertSame(1, preg_match($linkPattern, $messageBody, $matches));

        $page = HttpClient::create()->request(HttpMethodEnum::GET->value, $matches[0]);
        self::assertSame(ResponseStatusEnum::OK->value, $page->getStatusCode());
        self::assertFalse($this->isVerified(), 'Opening the link only shows the confirmation page.');

        $confirmation = sprintf(self::TOKEN_BODY_FORMAT, $matches[1]);
        $confirmed = $this->client->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, $confirmation);
        self::assertSame(ResponseStatusEnum::NO_CONTENT->value, $confirmed->getStatusCode());
        self::assertTrue($this->isVerified());

        $replay = $this->client->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, $confirmation);
        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, $replay->getStatusCode());
    }

    private function isVerified(): bool
    {
        $user = new PdoUserRepository($this->pdo)->findByEmail($this->email);
        self::assertNotNull($user);

        return $user->isVerified();
    }

    private function waitForMail(): string
    {
        $deadline = microtime(true) + self::MESSAGE_WAIT_TIMEOUT_SECONDS;

        do {
            $data = $this->mailpit->request(HttpMethodEnum::GET->value, self::MAILPIT_SEARCH_PATH, [
                'query' => ['query' => sprintf(self::MAILPIT_SEARCH_QUERY_FORMAT, $this->email)],
            ])->toArray();
            $message = $data['messages'][0] ?? null;
            $messageId = is_array($message) ? ($message['ID'] ?? null) : null;

            if (is_string($messageId)) {
                $this->mailpitMessageId = $messageId;

                return $this->messageText($messageId);
            }

            usleep(self::MESSAGE_POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for the confirmation email in Mailpit.');
    }

    private function messageText(string $messageId): string
    {
        $text = $this->mailpit->request(HttpMethodEnum::GET->value, sprintf(self::MAILPIT_MESSAGE_PATH, rawurlencode($messageId)))
            ->toArray()['Text'] ?? null;
        self::assertIsString($text);

        return $text;
    }
}
