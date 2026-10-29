<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\SessionApiClient;
use Tests\Functional\Support\TestUserData;

final class AuthApiTest extends TestCase
{
    private const string SESSION_PATH = '/api/v1/session';
    private const string REGISTRATIONS_PATH = '/api/v1/registrations';
    private const string CONFIRMATIONS_PATH = '/api/v1/email-confirmations';
    private const string EMAIL_PREFIX = 'auth-api-';
    private const string PASSWORD = 'functional-password-123';
    private const string CREDENTIALS_FORMAT = '{"email":"%s","password":"%s"}';
    private const string WRONG_PASSWORD = 'not-the-password-123';
    private const string INVALID_EMAIL = 'not-an-email-address';
    // bcrypt reads only the first 72 bytes, so a longer password would be cut silently.
    private const int TOO_LONG_PASSWORD_BYTES = 73;
    private const string UNKNOWN_TOKEN_BODY = '{"token":"unknown-confirmation-token"}';
    private const string USER_EXISTS_QUERY = 'SELECT COUNT(*) FROM users WHERE email = :email AND email_verified_at IS NULL';

    private PDO $pdo;
    private SessionApiClient $client;
    private string $email;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->client = new SessionApiClient();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);
    }

    public function testStartsAnonymousAndIssuesACsrfToken(): void
    {
        $session = $this->assertResponse(HttpMethodEnum::GET, self::SESSION_PATH, ResponseStatusEnum::OK, $this->client->get(self::SESSION_PATH));

        self::assertFalse($session['authenticated']);
        self::assertNull($session['email']);
    }

    public function testRejectsStateChangesWithoutTheCsrfToken(): void
    {
        $response = $this->client->sendWithoutCsrfToken(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, $this->credentials());

        $this->assertResponse(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, ResponseStatusEnum::FORBIDDEN, $response);
        self::assertSame(0, $this->countUnverifiedUsers());
    }

    public function testRejectsASignInWithoutTheCsrfToken(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::PASSWORD);

        $response = $this->client->sendWithoutCsrfToken(HttpMethodEnum::POST, self::SESSION_PATH, $this->credentials());

        $this->assertResponse(HttpMethodEnum::POST, self::SESSION_PATH, ResponseStatusEnum::FORBIDDEN, $response);
        self::assertFalse(ApiClient::json($this->client->get(self::SESSION_PATH))['authenticated']);
    }

    public function testRejectsAWrongPassword(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::PASSWORD);

        $response = $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, $this->credentials(self::WRONG_PASSWORD));

        $this->assertResponse(HttpMethodEnum::POST, self::SESSION_PATH, ResponseStatusEnum::UNAUTHORIZED, $response);
        self::assertFalse(ApiClient::json($this->client->get(self::SESSION_PATH))['authenticated']);
    }

    public function testRefusesToSignInAnAccountWhoseEmailIsNotConfirmed(): void
    {
        $this->client->send(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, $this->credentials());

        $response = $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, $this->credentials());

        $this->assertResponse(HttpMethodEnum::POST, self::SESSION_PATH, ResponseStatusEnum::FORBIDDEN, $response);
        self::assertFalse(ApiClient::json($this->client->get(self::SESSION_PATH))['authenticated']);
    }

    public function testRejectsRegistrationsWithAnInvalidEmailOrAPasswordBcryptWouldCut(): void
    {
        $invalidEmail = $this->client->send(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, sprintf(self::CREDENTIALS_FORMAT, self::INVALID_EMAIL, self::PASSWORD));
        $tooLongPassword = $this->client->send(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, $this->credentials(str_repeat('p', self::TOO_LONG_PASSWORD_BYTES)));

        $this->assertResponse(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $invalidEmail);
        $this->assertResponse(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $tooLongPassword);
        self::assertSame(0, $this->countUnverifiedUsers());
    }

    public function testSignsAVerifiedUserInAndOut(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::PASSWORD);
        $anonymous = ApiClient::json($this->client->get(self::SESSION_PATH));

        $signedIn = $this->assertResponse(HttpMethodEnum::POST, self::SESSION_PATH, ResponseStatusEnum::OK, $this->client->send(HttpMethodEnum::POST, self::SESSION_PATH, $this->credentials()));
        $current = ApiClient::json($this->client->get(self::SESSION_PATH));
        $this->assertResponse(HttpMethodEnum::DELETE, self::SESSION_PATH, ResponseStatusEnum::NO_CONTENT, $this->client->send(HttpMethodEnum::DELETE, self::SESSION_PATH));
        $afterSignOut = ApiClient::json($this->client->get(self::SESSION_PATH));

        self::assertSame($this->email, $signedIn['email']);
        self::assertFalse($signedIn['merchant'], 'Signing up gives no merchant access.');
        self::assertNotSame($anonymous['csrf_token'], $signedIn['csrf_token'], 'Signing in rotates the CSRF token.');
        self::assertTrue($current['authenticated']);
        self::assertFalse($afterSignOut['authenticated']);
    }

    public function testRegistersAnUnverifiedAccountAndRejectsUnknownTokens(): void
    {
        $this->assertResponse(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, ResponseStatusEnum::ACCEPTED, $this->client->send(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, $this->credentials()));
        $this->assertResponse(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, ResponseStatusEnum::UNPROCESSABLE_CONTENT, $this->client->send(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, self::UNKNOWN_TOKEN_BODY));

        self::assertSame(1, $this->countUnverifiedUsers());
    }

    /** @return array<string, mixed> */
    private function assertResponse(HttpMethodEnum $method, string $path, ResponseStatusEnum $status, ResponseInterface $response): array
    {
        self::assertSame($status->value, $response->getStatusCode(), (string) $response->getBody());
        ApiClient::assertMatchesSpecification($path, $method->value, $response);

        return (string) $response->getBody() === (string) null ? [] : ApiClient::json($response);
    }

    private function credentials(string $password = self::PASSWORD): string
    {
        return sprintf(self::CREDENTIALS_FORMAT, $this->email, $password);
    }

    private function countUnverifiedUsers(): int
    {
        $statement = $this->pdo->prepare(self::USER_EXISTS_QUERY);
        $statement->execute(['email' => $this->email]);

        return (int) $statement->fetchColumn();
    }
}
