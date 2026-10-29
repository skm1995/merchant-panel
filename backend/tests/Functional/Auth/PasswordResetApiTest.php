<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\Browser;
use Tests\Functional\Support\Mailpit;
use Tests\Functional\Support\SessionApiClient;
use Tests\Functional\Support\TestUserData;

final class PasswordResetApiTest extends TestCase
{
    private const string SESSION_PATH = '/api/v1/session';
    private const string REGISTRATIONS_PATH = '/api/v1/registrations';
    private const string RESETS_PATH = '/api/v1/password-resets';
    private const string CONFIRMATIONS_PATH = '/api/v1/password-reset-confirmations';
    private const string RESET_PAGE_PATH = '/reset-password';
    private const string RESET_LINK_PATTERN = '#%s\?token=([a-f0-9]{64})#';
    private const string EMAIL_PREFIX = 'password-reset-';
    private const string OLD_PASSWORD = 'old-password-123';
    private const string NEW_PASSWORD = 'new-password-456';
    private const string WEAK_PASSWORD = 'short';
    private const string INVALID_EMAIL = 'not-an-email-address';
    private const string EXPIRED_TOKEN = 'expired-reset-token';
    private const string EMAIL_BODY_FORMAT = '{"email":"%s"}';
    private const string CREDENTIALS_FORMAT = '{"email":"%s","password":"%s"}';
    private const string RESET_BODY_FORMAT = '{"token":"%s","password":"%s"}';
    private const string TOKEN_HASH_QUERY = 'SELECT token_hash FROM password_reset_tokens WHERE email = :email';
    private const string INSERT_EXPIRED_TOKEN_QUERY = 'INSERT INTO password_reset_tokens (email, token_hash, expires_at, created_at) VALUES (:email, :token_hash, NOW() - INTERVAL 1 MINUTE, NOW() - INTERVAL 61 MINUTE)';

    private PDO $pdo;
    private Mailpit $mailpit;
    private string $email;
    /** @var list<string> */
    private array $mailpitMessageIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->mailpit = new Mailpit();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);

        foreach ($this->mailpitMessageIds as $messageId) {
            $this->mailpit->delete($messageId);
        }
    }

    public function testResetsThePasswordThroughTheEmailedLinkAndEndsEverySession(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::OLD_PASSWORD);
        $signedIn = new SessionApiClient();
        $signedIn->send(HttpMethodEnum::POST, self::SESSION_PATH, $this->credentials(self::OLD_PASSWORD));
        $visitor = new SessionApiClient();

        $requested = $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));
        $this->assertResponse(self::RESETS_PATH, ResponseStatusEnum::ACCEPTED, $requested);
        $link = $this->emailedLink();
        self::assertSame(ResponseStatusEnum::OK->value, HttpClient::create()->request(HttpMethodEnum::GET->value, $link[0])->getStatusCode());

        $reset = $visitor->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, sprintf(self::RESET_BODY_FORMAT, $link[1], self::NEW_PASSWORD));
        $replayed = $visitor->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, sprintf(self::RESET_BODY_FORMAT, $link[1], self::NEW_PASSWORD));

        $this->assertResponse(self::CONFIRMATIONS_PATH, ResponseStatusEnum::NO_CONTENT, $reset);
        $this->assertResponse(self::CONFIRMATIONS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $replayed);
        self::assertFalse(ApiClient::json($signedIn->get(self::SESSION_PATH))['authenticated'], 'The session signed in with the old password ended.');
        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $this->signIn(self::OLD_PASSWORD)->getStatusCode());
        self::assertSame(ResponseStatusEnum::OK->value, $this->signIn(self::NEW_PASSWORD)->getStatusCode());
    }

    public function testAnswersTheSameForAnUnknownOrUnconfirmedAddressWithoutSendingALink(): void
    {
        $visitor = new SessionApiClient();
        $unknown = $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));
        $visitor->send(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, $this->credentials(self::OLD_PASSWORD));
        $this->mailpitMessageIds[] = $this->mailpit->waitForMessageTo($this->email);

        $unconfirmed = $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));

        $this->assertResponse(self::RESETS_PATH, ResponseStatusEnum::ACCEPTED, $unknown);
        $this->assertResponse(self::RESETS_PATH, ResponseStatusEnum::ACCEPTED, $unconfirmed);
        self::assertNull($this->storedTokenHash(), 'No reset link exists for an address without a confirmed account.');
    }

    public function testSendsNoNewLinkWithinFiveMinutesOfTheLast(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::OLD_PASSWORD);
        $visitor = new SessionApiClient();
        $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));
        $firstToken = $this->storedTokenHash();

        $repeated = $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));
        $this->mailpitMessageIds[] = $this->mailpit->waitForMessageTo($this->email);

        $this->assertResponse(self::RESETS_PATH, ResponseStatusEnum::ACCEPTED, $repeated);
        self::assertNotNull($firstToken);
        self::assertSame($firstToken, $this->storedTokenHash(), 'The repeated request replaced no link and sent no email.');
    }

    public function testRefusesAnExpiredLinkAndAPasswordThatBreaksTheRules(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::OLD_PASSWORD);
        $this->pdo->prepare(self::INSERT_EXPIRED_TOKEN_QUERY)->execute(['email' => $this->email, 'token_hash' => hash('sha256', self::EXPIRED_TOKEN)]);
        $visitor = new SessionApiClient();

        $expired = $visitor->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, sprintf(self::RESET_BODY_FORMAT, self::EXPIRED_TOKEN, self::NEW_PASSWORD));
        $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));
        $token = $this->emailedLink()[1];
        $weak = $visitor->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, sprintf(self::RESET_BODY_FORMAT, $token, self::WEAK_PASSWORD));
        $valid = $visitor->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, sprintf(self::RESET_BODY_FORMAT, $token, self::NEW_PASSWORD));

        $this->assertResponse(self::CONFIRMATIONS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $expired);
        $this->assertResponse(self::CONFIRMATIONS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $weak);
        $this->assertResponse(self::CONFIRMATIONS_PATH, ResponseStatusEnum::NO_CONTENT, $valid, 'A refused password leaves the link usable.');
    }

    public function testRejectsAnInvalidAddressAndRequestsWithoutTheCsrfToken(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::OLD_PASSWORD);
        $visitor = new SessionApiClient();

        $invalid = $visitor->send(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, self::INVALID_EMAIL));
        $withoutCsrf = $visitor->sendWithoutCsrfToken(HttpMethodEnum::POST, self::RESETS_PATH, sprintf(self::EMAIL_BODY_FORMAT, $this->email));
        $resetWithoutCsrf = $visitor->sendWithoutCsrfToken(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, sprintf(self::RESET_BODY_FORMAT, self::EXPIRED_TOKEN, self::NEW_PASSWORD));

        $this->assertResponse(self::RESETS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $invalid);
        $this->assertResponse(self::RESETS_PATH, ResponseStatusEnum::FORBIDDEN, $withoutCsrf);
        $this->assertResponse(self::CONFIRMATIONS_PATH, ResponseStatusEnum::FORBIDDEN, $resetWithoutCsrf);
        self::assertNull($this->storedTokenHash());
    }

    private function assertResponse(string $path, ResponseStatusEnum $status, ResponseInterface $response, string $message = ''): void
    {
        self::assertSame($status->value, $response->getStatusCode(), $message . (string) $response->getBody());
        ApiClient::assertMatchesSpecification($path, HttpMethodEnum::POST->value, $response);
    }

    /**
     * The reset link from the email, and its token.
     *
     * @return array{string, string}
     */
    private function emailedLink(): array
    {
        $messageId = $this->mailpit->waitForMessageTo($this->email);
        $this->mailpitMessageIds[] = $messageId;
        $pattern = sprintf(self::RESET_LINK_PATTERN, preg_quote(Browser::url(self::RESET_PAGE_PATH), '#'));
        self::assertSame(1, preg_match($pattern, $this->mailpit->text($messageId), $matches));

        return [$matches[0], $matches[1]];
    }

    private function signIn(string $password): ResponseInterface
    {
        return new SessionApiClient()->send(HttpMethodEnum::POST, self::SESSION_PATH, $this->credentials($password));
    }

    private function credentials(string $password): string
    {
        return sprintf(self::CREDENTIALS_FORMAT, $this->email, $password);
    }

    private function storedTokenHash(): ?string
    {
        $statement = $this->pdo->prepare(self::TOKEN_HASH_QUERY);
        $statement->execute(['email' => $this->email]);
        $hash = $statement->fetchColumn();

        return is_string($hash) ? $hash : null;
    }
}
