<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Repository\PdoEmailVerificationTokenRepository;
use App\Auth\Repository\PdoUserRepository;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\LogoutUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Session;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AuthFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'functional-';
    private const string EMAIL_DOMAIN = '@example.com';
    private const string PASSWORD = 'functional-password-123';
    private const string INCORRECT_PASSWORD = 'incorrect-password';
    private const string INVALID_CREDENTIALS_MESSAGE = 'Invalid email or password.';
    private const string TEST_DATABASE_NAME = 'app_test';
    private const string CURRENT_DATABASE_QUERY = 'SELECT DATABASE()';
    private const string MARK_VERIFIED_QUERY = 'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE email = :email';
    private const string TOKEN_HASH_ALGORITHM = 'sha256';
    private const string INITIAL_TOKEN = 'initial-token';
    private const string RESEND_TOKEN_FORMAT = 'resend-token-%d';
    private const string OVER_LIMIT_TOKEN = 'over-limit-token';
    private const string NEXT_WINDOW_TOKEN = 'next-window-token';
    private const string TWO_DAYS_MODIFIER = '+2 days';
    private const string SECONDS_MODIFIER_FORMAT = '+%d seconds';
    private const string USER_EMAIL_SESSION_KEY = 'user_email';
    private const int RESEND_WINDOW_SECONDS = 86400;
    private const int MAXIMUM_RESEND_ATTEMPTS = 5;
    private const string DELETE_QUERY = 'DELETE FROM users WHERE email = :email';
    private const string DELETE_TOKEN_QUERY = 'DELETE FROM email_verification_tokens WHERE email = :email';

    private string $email;
    private RegisterUserService $registerUser;
    private LoginUserService $loginUser;
    private LogoutUserService $logoutUser;
    private PdoUserRepository $users;
    private PdoEmailVerificationTokenRepository $tokens;
    private Session $session;
    private PDO $pdo;
    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->users = new PdoUserRepository($this->pdo);
        $userAuthService = new UserAuthService($this->users);
        $this->tokens = new PdoEmailVerificationTokenRepository($this->pdo);
        $this->registerUser = new RegisterUserService(
            $userAuthService,
            $this->tokens,
            $this->createStub(ConfirmationEmailQueueInterface::class),
        );
        $this->session = new Session();

        if (session_status() === PHP_SESSION_NONE) {
            $this->session->start();
        }

        $this->loginUser = new LoginUserService($userAuthService, $this->session);
        $this->logoutUser = new LogoutUserService($this->session);
        $this->originalSession = $_SESSION;
        $randomSuffix = random_bytes(8) |> bin2hex(...);
        $this->email = self::EMAIL_PREFIX . $randomSuffix . self::EMAIL_DOMAIN;
    }

    protected function tearDown(): void
    {
        foreach ([self::DELETE_TOKEN_QUERY, self::DELETE_QUERY] as $query) {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['email' => $this->email]);
        }

        $_SESSION = $this->originalSession;
    }

    public function testRegistersAndAuthenticatesUserPersistedInMysql(): void
    {
        $result = $this->registerUser->execute(self::credentials($this->email, self::PASSWORD));
        $markVerified = $this->pdo->prepare(self::MARK_VERIFIED_QUERY);
        $markVerified->execute(['email' => $this->email]);
        $registeredUser = $this->users->findByEmail($this->email);
        unset($_SESSION[self::USER_EMAIL_SESSION_KEY]);
        $loginResult = $this->loginUser->execute(self::credentials($this->email, self::PASSWORD));

        self::assertNotNull($registeredUser);
        self::assertNotNull($registeredUser->emailVerifiedAt);
        self::assertSame($this->email, $registeredUser->email);
        self::assertNotSame(self::PASSWORD, $registeredUser->password);
        self::assertTrue(password_verify(self::PASSWORD, $registeredUser->password));
        self::assertSame('/confirmation-sent', $result->redirectPath);
        self::assertSame('/', $loginResult->redirectPath);
        self::assertSame($this->email, $this->session->get(self::USER_EMAIL_SESSION_KEY));
        $this->logoutUser->execute();
        self::assertNull($this->session->get(self::USER_EMAIL_SESSION_KEY));
    }

    public function testFunctionalSuiteUsesDedicatedTestDatabase(): void
    {
        $statement = $this->pdo->query(self::CURRENT_DATABASE_QUERY);
        self::assertInstanceOf(PDOStatement::class, $statement);

        self::assertSame(self::TEST_DATABASE_NAME, $statement->fetchColumn());
    }

    public function testConfirmationEmailResendsAreLimitedToFivePerDay(): void
    {
        $this->users->create($this->email, password_hash(self::PASSWORD, PASSWORD_DEFAULT));
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify(self::TWO_DAYS_MODIFIER);
        $this->tokens->store($this->email, hash(self::TOKEN_HASH_ALGORITHM, self::INITIAL_TOKEN), $expiresAt);

        for ($attempt = 1; $attempt <= self::MAXIMUM_RESEND_ATTEMPTS; $attempt++) {
            self::assertTrue($this->tokens->resend(
                $this->email,
                hash(self::TOKEN_HASH_ALGORITHM, sprintf(self::RESEND_TOKEN_FORMAT, $attempt)),
                $expiresAt,
                $now,
                self::RESEND_WINDOW_SECONDS,
                self::MAXIMUM_RESEND_ATTEMPTS,
            ));
        }

        self::assertFalse($this->tokens->resend(
            $this->email,
            hash(self::TOKEN_HASH_ALGORITHM, self::OVER_LIMIT_TOKEN),
            $expiresAt,
            $now,
            self::RESEND_WINDOW_SECONDS,
            self::MAXIMUM_RESEND_ATTEMPTS,
        ));
        self::assertTrue($this->tokens->resend(
            $this->email,
            hash(self::TOKEN_HASH_ALGORITHM, self::NEXT_WINDOW_TOKEN),
            $expiresAt,
            $now->modify(sprintf(self::SECONDS_MODIFIER_FORMAT, self::RESEND_WINDOW_SECONDS + 1)),
            self::RESEND_WINDOW_SECONDS,
            self::MAXIMUM_RESEND_ATTEMPTS,
        ));
    }

    public function testRejectsIncorrectPasswordForPersistedUser(): void
    {
        $this->registerUser->execute(self::credentials($this->email, self::PASSWORD));
        $markVerified = $this->pdo->prepare(self::MARK_VERIFIED_QUERY);
        $markVerified->execute(['email' => $this->email]);

        $result = $this->loginUser->execute(self::credentials($this->email, self::INCORRECT_PASSWORD));

        self::assertSame(self::INVALID_CREDENTIALS_MESSAGE, $result->errorMessage);
    }

    /** @return array<string, string> */
    private static function credentials(string $email, string $password): array
    {
        return ['email' => $email, 'password' => $password];
    }
}
