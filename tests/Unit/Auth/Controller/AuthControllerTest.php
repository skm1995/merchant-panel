<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Controller;

use App\Auth\Controller\AuthController;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\LogoutUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private const string EMAIL_FIELD = 'email';
    private const string PASSWORD_FIELD = 'password';
    private const string INVALID_EMAIL = 'invalid';
    private const string PASSWORD_VALUE = 'long-enough-password';
    private const string VALIDATION_ERROR = 'Enter a valid email address.';
    private const string CSRF_TOKEN_FIELD = '_csrf_token';

    private Session $session;
    private CsrfToken $csrfToken;
    /** @var array<string, mixed> */
    private array $originalSession;
    /** @var array<string, mixed> */
    private array $originalPost;

    protected function setUp(): void
    {
        $this->session = new Session();

        if (session_status() === PHP_SESSION_NONE) {
            $this->session->start();
        }

        $this->originalSession = $_SESSION;
        $this->originalPost = $_POST;
        $_SESSION = [];
        $this->csrfToken = new CsrfToken($this->session);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        $_POST = $this->originalPost;
    }

    public function testRegistrationDisplaysValidationError(): void
    {
        $_POST = [
            self::EMAIL_FIELD => self::INVALID_EMAIL,
            self::PASSWORD_FIELD => self::PASSWORD_VALUE,
            self::CSRF_TOKEN_FIELD => $this->csrfToken->get(),
        ];
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('create');
        $controller = $this->controller($users);
        ob_start();

        $controller->register();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::VALIDATION_ERROR, $output);
        self::assertStringContainsString(self::INVALID_EMAIL, $output);
    }

    public function testLoginPageRendersForm(): void
    {
        $controller = $this->controller($this->createStub(UserRepositoryInterface::class));
        ob_start();

        $controller->showLogin();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('action="/login"', $output);
    }

    private function controller(UserRepositoryInterface $users): AuthController
    {
        $userAuthService = new UserAuthService($users);
        $tokens = $this->createStub(EmailVerificationTokenRepositoryInterface::class);

        return new AuthController(
            new RegisterUserService(
                $userAuthService,
                $tokens,
                $this->createStub(ConfirmationEmailQueueInterface::class),
            ),
            new LoginUserService($userAuthService, $this->session),
            new LogoutUserService($this->session),
            new ConfirmEmailService($tokens),
            $this->csrfToken,
        );
    }
}
