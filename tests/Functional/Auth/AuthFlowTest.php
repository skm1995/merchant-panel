<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Repository\PdoEmailVerificationTokenRepository;
use App\Auth\Repository\PdoUserRepository;
use App\Auth\Service\ConfirmationToken;
use App\Auth\Service\CurrentUser;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Session;
use DateTimeImmutable;
use DomainException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

final class AuthFlowTest extends TestCase
{
    private const string EMAIL_PREFIX = 'functional-';
    private const string PASSWORD = 'functional-password-123';
    private const string INCORRECT_PASSWORD = 'incorrect-password';
    private const string INVALID_CREDENTIALS_MESSAGE = 'Invalid email or password.';
    private const string TEST_DATABASE_NAME = 'app_test';
    private const string CURRENT_DATABASE_QUERY = 'SELECT DATABASE()';
    private const string MARK_VERIFIED_QUERY = 'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE email = :email';
    private const string EMAIL_PARAMETER = 'email';
    private const string INITIAL_TOKEN = 'initial-token';
    private const string RESEND_TOKEN_FORMAT = 'resend-token-%d';
    private const string OVER_LIMIT_TOKEN = 'over-limit-token';
    private const string NEXT_WINDOW_TOKEN = 'next-window-token';
    private const string TWO_DAYS_MODIFIER = '+2 days';
    private const string SECONDS_MODIFIER_FORMAT = '+%d seconds';

    private string $email;
    private RegisterUserService $registerUser;
    private LoginUserService $loginUser;
    private CurrentUser $currentUser;
    private PdoUserRepository $users;
    private PdoEmailVerificationTokenRepository $tokens;
    private PDO $pdo;
    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->users = new PdoUserRepository($this->pdo);
        $this->tokens = new PdoEmailVerificationTokenRepository($this->pdo);
        $userAuthService = new UserAuthService($this->users);
        $this->registerUser = new RegisterUserService(
            $userAuthService,
            $this->tokens,
            $this->createStub(ConfirmationEmailQueueInterface::class),
        );
        $session = new Session();
        $session->start();
        $this->originalSession = $_SESSION;
        $this->currentUser = new CurrentUser($session);
        $this->loginUser = new LoginUserService($userAuthService, $this->currentUser);
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);
        $_SESSION = $this->originalSession;
    }

    public function testRegistersAndAuthenticatesUserPersistedInMysql(): void
    {
        $this->registerUser->execute($this->email, self::PASSWORD);
        $this->markVerified();
        $registeredUser = $this->users->findByEmail($this->email);

        $this->loginUser->execute($this->email, self::PASSWORD);

        self::assertNotNull($registeredUser);
        self::assertTrue($registeredUser->isVerified());
        self::assertNotSame(self::PASSWORD, $registeredUser->passwordHash);
        self::assertTrue($registeredUser->hasPassword(self::PASSWORD));
        self::assertSame($this->email, $this->currentUser->email());
        $this->currentUser->signOut();
        self::assertNull($this->currentUser->email());
    }

    public function testFunctionalSuiteUsesDedicatedTestDatabase(): void
    {
        $statement = $this->pdo->query(self::CURRENT_DATABASE_QUERY);
        self::assertInstanceOf(PDOStatement::class, $statement);

        self::assertSame(self::TEST_DATABASE_NAME, $statement->fetchColumn());
    }

    public function testUniqueIndexViolationIsReportedAsDuplicateEmail(): void
    {
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $this->users->create($this->email, $passwordHash);

        $this->expectException(DuplicateEmailException::class);
        $this->users->create($this->email, $passwordHash);
    }

    public function testConfirmationEmailResendsAreLimitedPerWindowAndResetAfterIt(): void
    {
        $this->users->create($this->email, password_hash(self::PASSWORD, PASSWORD_DEFAULT));
        $now = new DateTimeImmutable();
        $this->tokens->store($this->email, self::tokenHash(self::INITIAL_TOKEN), $now->modify(self::TWO_DAYS_MODIFIER));

        for ($attempt = 1; $attempt <= RegisterUserService::MAXIMUM_RESEND_ATTEMPTS; $attempt++) {
            self::assertTrue($this->resend(sprintf(self::RESEND_TOKEN_FORMAT, $attempt), $now));
        }

        self::assertFalse($this->resend(self::OVER_LIMIT_TOKEN, $now));
        self::assertTrue($this->resend(
            self::NEXT_WINDOW_TOKEN,
            $now->modify(sprintf(self::SECONDS_MODIFIER_FORMAT, RegisterUserService::RESEND_WINDOW_SECONDS + 1)),
        ));
    }

    public function testRejectsIncorrectPasswordForPersistedUser(): void
    {
        $this->registerUser->execute($this->email, self::PASSWORD);
        $this->markVerified();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(self::INVALID_CREDENTIALS_MESSAGE);
        $this->loginUser->execute($this->email, self::INCORRECT_PASSWORD);
    }

    private function resend(string $token, DateTimeImmutable $now): bool
    {
        return $this->tokens->storeWithinResendLimit(
            $this->email,
            self::tokenHash($token),
            $now->modify(self::TWO_DAYS_MODIFIER),
            $now,
            RegisterUserService::RESEND_WINDOW_SECONDS,
            RegisterUserService::MAXIMUM_RESEND_ATTEMPTS,
        );
    }

    private function markVerified(): void
    {
        $this->pdo->prepare(self::MARK_VERIFIED_QUERY)->execute([self::EMAIL_PARAMETER => $this->email]);
    }

    private static function tokenHash(string $token): string
    {
        return ConfirmationToken::fromString($token)->hash();
    }
}
