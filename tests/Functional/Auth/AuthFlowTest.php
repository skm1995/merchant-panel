<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Core\DatabaseConnection;
use App\Enum\ResponseStatusEnum;
use App\Repository\PdoUserRepository;
use App\Repository\PdoEmailVerificationTokenRepository;
use App\Service\Auth\ConfirmationEmailQueueInterface;
use App\Service\Auth\ConfirmEmailService;
use App\Service\Auth\AuthService;
use App\Service\Auth\UserAuthService;
use App\Service\Auth\LoginUserService;
use App\Service\Auth\LogoutUserService;
use App\Service\Auth\RegisterUserService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AuthFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'functional-';
    private const string EMAIL_DOMAIN = '@example.com';
    private const string PASSWORD = 'functional-password-123';
    private const string INCORRECT_PASSWORD = 'incorrect-password';
    private const string EMAIL_INPUT_KEY = 'email';
    private const string PASSWORD_INPUT_KEY = 'password';
    private const string USER_EMAIL_SESSION_KEY = 'user_email';
    private const string DELETE_QUERY = 'DELETE FROM users WHERE email = :email';
    private const string EMAIL_PARAMETER = 'email';
    private const string INVALID_CREDENTIALS_MESSAGE = 'Invalid email or password.';
    private const string TEST_DATABASE_NAME = 'app_test';
    private const string CURRENT_DATABASE_QUERY = 'SELECT DATABASE()';
    private const string MARK_VERIFIED_QUERY = 'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE email = :email';
    private const string DELETE_TOKEN_QUERY = 'DELETE FROM email_verification_tokens WHERE email = :email';

    private string $email;
    private AuthService $authService;
    private PdoUserRepository $users;
    private PDO $pdo;
    /** @var array<string, mixed> */
    private array $originalSession;
    /** @var array<string, mixed> */
    private array $originalPost;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->users = new PdoUserRepository($this->pdo);
        $userAuthService = new UserAuthService($this->users);
        $tokens = new PdoEmailVerificationTokenRepository($this->pdo);
        $this->authService = new AuthService(
            new RegisterUserService(
                $userAuthService,
                $tokens,
                $this->createStub(ConfirmationEmailQueueInterface::class),
            ),
            new LoginUserService($userAuthService),
            new LogoutUserService(),
            new ConfirmEmailService($tokens),
        );
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->originalSession = $_SESSION;
        $this->originalPost = $_POST;
        $randomSuffix = random_bytes(8) |> bin2hex(...);
        $this->email = self::EMAIL_PREFIX . $randomSuffix . self::EMAIL_DOMAIN;
    }

    protected function tearDown(): void
    {
        $tokenDelete = $this->pdo->prepare(self::DELETE_TOKEN_QUERY);
        $tokenDelete->execute([self::EMAIL_PARAMETER => $this->email]);
        $statement = $this->pdo->prepare(self::DELETE_QUERY);
        $statement->execute([self::EMAIL_PARAMETER => $this->email]);
        $_SESSION = $this->originalSession;
        $_POST = $this->originalPost;
    }

    public function testRegistersAndAuthenticatesUserPersistedInMysql(): void
    {
        $input = [
            self::EMAIL_INPUT_KEY => $this->email,
            self::PASSWORD_INPUT_KEY => self::PASSWORD,
        ];
        $_POST = $input;
        $this->authService->register();
        $registeredUser = $this->users->findByEmail($this->email);
        $markVerified = $this->pdo->prepare(self::MARK_VERIFIED_QUERY);
        $markVerified->execute([self::EMAIL_PARAMETER => $this->email]);
        $registeredUser = $this->users->findByEmail($this->email);
        unset($_SESSION[self::USER_EMAIL_SESSION_KEY]);
        $this->authService->login();

        self::assertNotNull($registeredUser);
        self::assertNotNull($registeredUser->emailVerifiedAt);
        self::assertSame($this->email, $registeredUser->email);
        self::assertNotSame(self::PASSWORD, $registeredUser->password);
        self::assertTrue(password_verify(self::PASSWORD, $registeredUser->password));
        self::assertSame(ResponseStatusEnum::SEE_OTHER->value, http_response_code());
        self::assertSame($this->email, $_SESSION[self::USER_EMAIL_SESSION_KEY]);
        $this->authService->logout();
        self::assertArrayNotHasKey(self::USER_EMAIL_SESSION_KEY, $_SESSION);
    }

    public function testFunctionalSuiteUsesDedicatedTestDatabase(): void
    {
        $statement = $this->pdo->query(self::CURRENT_DATABASE_QUERY);
        self::assertInstanceOf(PDOStatement::class, $statement);
        $databaseName = $statement->fetchColumn();

        self::assertSame(self::TEST_DATABASE_NAME, $databaseName);
    }

    public function testRejectsIncorrectPasswordForPersistedUser(): void
    {
        $_POST = [
            self::EMAIL_INPUT_KEY => $this->email,
            self::PASSWORD_INPUT_KEY => self::PASSWORD,
        ];
        $this->authService->register();

        $_POST = [
            self::EMAIL_INPUT_KEY => $this->email,
            self::PASSWORD_INPUT_KEY => self::INCORRECT_PASSWORD,
        ];
        ob_start();
        $this->authService->login();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::INVALID_CREDENTIALS_MESSAGE, $output);
    }
}
