<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Functional\Support\Browser;
use Tests\Functional\Support\TestUserData;

final class AsyncConfirmationFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'async-confirmation-';
    private const string PASSWORD = 'functional-password-123';
    private const string REGISTER_PATH = '/register';
    private const string REGISTER_BUTTON = 'Register';
    private const string CONFIRM_BUTTON = 'Confirm email';
    private const string ERROR_SELECTOR = 'main p';
    private const string INVALID_CONFIRMATION_MESSAGE = 'This confirmation link is invalid or has expired.';
    private const string CONFIRMATION_LINK_PATTERN = '#%s\?token=[a-f0-9]{64}#';
    private const string CONFIRM_PATH = '/confirm';
    private const string MAILHOG_API_BASE_URL_ENVIRONMENT_KEY = 'MAILHOG_API_BASE_URL';
    private const string MAILHOG_SEARCH_PATH = '/api/v2/search';
    private const string MAILHOG_MESSAGE_PATH = '/api/v1/messages/%s';
    private const string URL_PATH_SEPARATOR = '/';
    private const string DELETE_METHOD = 'DELETE';
    private const int MESSAGE_WAIT_TIMEOUT_SECONDS = 15;
    private const int MESSAGE_POLL_INTERVAL_MICROSECONDS = 100000;

    private string $email;
    private PDO $pdo;
    private HttpBrowser $browser;
    private HttpClientInterface $mailhog;
    private ?string $mailhogMessageId = null;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
        $this->browser = Browser::create();
        $this->mailhog = HttpClient::createForBaseUri(rtrim(
            Environment::get(self::MAILHOG_API_BASE_URL_ENVIRONMENT_KEY),
            self::URL_PATH_SEPARATOR,
        ));
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);

        if ($this->mailhogMessageId !== null) {
            $this->mailhog->request(self::DELETE_METHOD, sprintf(
                self::MAILHOG_MESSAGE_PATH,
                rawurlencode($this->mailhogMessageId),
            ))->getStatusCode();
        }
    }

    public function testRegistrationEmailIsSentAsynchronouslyAndConfirmsPersistedUser(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::REGISTER_PATH));
        $this->browser->submitForm(self::REGISTER_BUTTON, ['email' => $this->email, 'password' => self::PASSWORD]);

        $linkPattern = sprintf(self::CONFIRMATION_LINK_PATTERN, preg_quote(Browser::url(self::CONFIRM_PATH), '#'));
        $messageBody = $this->waitForMail() |> quoted_printable_decode(...);
        self::assertSame(1, preg_match($linkPattern, $messageBody, $matches));

        $this->browser->request(HttpMethodEnum::GET->value, $matches[0]);
        self::assertFalse($this->isVerified());

        $this->browser->submitForm(self::CONFIRM_BUTTON);
        self::assertTrue($this->isVerified());

        $this->browser->back();
        $replay = $this->browser->submitForm(self::CONFIRM_BUTTON);
        self::assertSame(self::INVALID_CONFIRMATION_MESSAGE, $replay->filter(self::ERROR_SELECTOR)->text());
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
            $data = $this->mailhog->request(HttpMethodEnum::GET->value, self::MAILHOG_SEARCH_PATH, [
                'query' => ['kind' => 'to', 'query' => $this->email],
            ])->toArray();
            $message = $data['items'][0] ?? null;
            $messageId = is_array($message) ? ($message['ID'] ?? null) : null;
            $body = is_array($message) ? ($message['Content']['Body'] ?? null) : null;

            if (is_string($messageId) && is_string($body)) {
                $this->mailhogMessageId = $messageId;

                return $body;
            }

            usleep(self::MESSAGE_POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for the confirmation email in MailHog.');
    }
}
