<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Controller;

use App\Auth\Controller\AuthApiController;
use App\Auth\Entity\User;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\CurrentUser;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Session;
use App\Shared\Messaging\Outbox\OutboxInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class AuthApiControllerTest extends TestCase
{
    private const string SESSION_PATH = '/api/v1/session';
    private const string REGISTRATIONS_PATH = '/api/v1/registrations';
    private const string CONFIRMATIONS_PATH = '/api/v1/email-confirmations';
    private const string CSRF_HEADER = 'x-csrf-token';
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'long-enough-password';
    private const string CREDENTIALS = '{"email":"person@example.com","password":"long-enough-password"}';
    private const string WRONG_CREDENTIALS = '{"email":"person@example.com","password":"wrong-password"}';

    private Session $session;
    private CsrfToken $csrfToken;
    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->session = new Session();
        $this->session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->csrfToken = new CsrfToken($this->session);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testDescribesAnAnonymousSessionWithItsCsrfToken(): void
    {
        $body = HttpHarness::json(new Request(HttpMethodEnum::GET->value, self::SESSION_PATH), $this->controller(null));

        self::assertFalse($body['authenticated']);
        self::assertNull($body['email']);
        self::assertSame($this->csrfToken->get(), $body['csrf_token']);
    }

    public function testRejectsSignInWithoutTheCsrfHeader(): void
    {
        HttpHarness::dispatch(new Request(HttpMethodEnum::POST->value, self::SESSION_PATH, body: self::CREDENTIALS), $this->controller(self::verifiedUser()));

        self::assertSame(ResponseStatusEnum::FORBIDDEN->value, http_response_code());
        self::assertNull(new CurrentUser($this->session)->email());
    }

    public function testSignsInAVerifiedUser(): void
    {
        $body = HttpHarness::json($this->unsafe(HttpMethodEnum::POST, self::SESSION_PATH, self::CREDENTIALS), $this->controller(self::verifiedUser()));

        self::assertSame(ResponseStatusEnum::OK->value, http_response_code());
        self::assertTrue($body['authenticated']);
        self::assertSame(self::EMAIL, $body['email']);
        self::assertSame(self::EMAIL, new CurrentUser($this->session)->email());
    }

    public function testRejectsWrongCredentialsAndUnverifiedUsers(): void
    {
        HttpHarness::dispatch($this->unsafe(HttpMethodEnum::POST, self::SESSION_PATH, self::WRONG_CREDENTIALS), $this->controller(self::verifiedUser()));
        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());

        HttpHarness::dispatch($this->unsafe(HttpMethodEnum::POST, self::SESSION_PATH, self::CREDENTIALS), $this->controller(self::unverifiedUser()));
        self::assertSame(ResponseStatusEnum::FORBIDDEN->value, http_response_code());
    }

    public function testSignsOut(): void
    {
        new CurrentUser($this->session)->signIn(self::verifiedUser());

        $output = HttpHarness::dispatch($this->unsafe(HttpMethodEnum::DELETE, self::SESSION_PATH), $this->controller(self::verifiedUser()));

        self::assertSame(ResponseStatusEnum::NO_CONTENT->value, http_response_code());
        self::assertSame(0, strlen((string) $output));
        self::assertNull(new CurrentUser($this->session)->email());
    }

    public function testAcceptsARegistrationAndRejectsInvalidOnes(): void
    {
        HttpHarness::dispatch($this->unsafe(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, self::CREDENTIALS), $this->controller(null));
        self::assertSame(ResponseStatusEnum::ACCEPTED->value, http_response_code());

        HttpHarness::dispatch($this->unsafe(HttpMethodEnum::POST, self::REGISTRATIONS_PATH, '{"email":"invalid","password":"long-enough-password"}'), $this->controller(null));
        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, http_response_code());
    }

    public function testRejectsAnUnknownConfirmationToken(): void
    {
        HttpHarness::dispatch($this->unsafe(HttpMethodEnum::POST, self::CONFIRMATIONS_PATH, '{"token":"unknown"}'), $this->controller(null));

        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, http_response_code());
    }

    private function unsafe(HttpMethodEnum $method, string $path, ?string $body = null): Request
    {
        $token = $this->csrfToken->get() ?? $this->csrfToken->create();

        return new Request($method->value, $path, headers: [self::CSRF_HEADER => $token], body: $body);
    }

    private static function verifiedUser(): User
    {
        return new User(self::EMAIL, password_hash(self::PASSWORD, PASSWORD_DEFAULT), new DateTimeImmutable(), new DateTimeImmutable());
    }

    private static function unverifiedUser(): User
    {
        return new User(self::EMAIL, password_hash(self::PASSWORD, PASSWORD_DEFAULT), new DateTimeImmutable());
    }

    private function controller(?User $existingUser): AuthApiController
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($existingUser);
        $users->method('create')->willReturnCallback(
            static fn (string $email, string $hash): User => new User($email, $hash, new DateTimeImmutable()),
        );
        $tokens = $this->createStub(EmailVerificationTokenRepositoryInterface::class);
        $tokens->method('confirmEmail')->willReturn(false);
        $userAuthService = new UserAuthService($users);
        $currentUser = new CurrentUser($this->session);

        return new AuthApiController(
            new RegisterUserService($userAuthService, $tokens, $this->createStub(OutboxInterface::class), new ImmediateTransactionRunner(), new FrozenClock()),
            new LoginUserService($userAuthService, $currentUser),
            new ConfirmEmailService($tokens, new ImmediateTransactionRunner(), new FrozenClock()),
            $currentUser,
            $this->csrfToken,
            HttpHarness::responder($this->session),
        );
    }
}
