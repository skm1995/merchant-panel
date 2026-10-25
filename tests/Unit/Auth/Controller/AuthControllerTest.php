<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Controller;

use App\Auth\Controller\AuthController;
use App\Auth\Message\ConfirmationEmailQueueInterface;
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
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private const string REGISTER_PATH = '/register';
    private const string CONFIRM_PATH = '/confirm';
    private const string EMAIL_FIELD = 'email';
    private const string PASSWORD_FIELD = 'password';
    private const string TOKEN_FIELD = 'token';
    private const string INVALID_EMAIL = 'invalid';
    private const string PASSWORD_VALUE = 'long-enough-password';
    private const string TOKEN_VALUE = 'unknown-token';
    private const string VALIDATION_ERROR = 'Enter a valid email address.';
    private const string INVALID_TOKEN_ERROR = 'This confirmation link is invalid or has expired.';
    private const string LOGIN_FORM = 'action="/login"';

    private Session $session;
    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->session = new Session();
        $this->session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testRegistrationDisplaysValidationErrorWithSubmittedEmail(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('create');
        $request = self::post(self::REGISTER_PATH, [
            self::EMAIL_FIELD => self::INVALID_EMAIL,
            self::PASSWORD_FIELD => self::PASSWORD_VALUE,
        ]);

        $output = $this->capture(fn () => $this->controller($users)->register($request));

        self::assertStringContainsString(self::VALIDATION_ERROR, $output);
        self::assertStringContainsString(self::INVALID_EMAIL, $output);
    }

    public function testRegistrationWithoutCredentialsIsInvalidRequest(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('findByEmail');

        $this->expectException(InvalidRequestException::class);
        $this->controller($users)->register(self::post(self::REGISTER_PATH, []));
    }

    public function testLoginPageRendersForm(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);

        $output = $this->capture(fn () => $this->controller($users)->showLogin());

        self::assertStringContainsString(self::LOGIN_FORM, $output);
    }

    public function testConfirmationPageWithoutTokenIsInvalidRequest(): void
    {
        $request = new Request(HttpMethodEnum::GET->value, self::CONFIRM_PATH);

        $this->expectException(InvalidRequestException::class);
        $this->controller($this->createStub(UserRepositoryInterface::class))->confirm($request);
    }

    public function testInvalidConfirmationTokenDisplaysError(): void
    {
        $request = self::post(self::CONFIRM_PATH, [self::TOKEN_FIELD => self::TOKEN_VALUE]);
        $users = $this->createStub(UserRepositoryInterface::class);

        $output = $this->capture(fn () => $this->controller($users)->confirmPost($request));

        self::assertStringContainsString(self::INVALID_TOKEN_ERROR, $output);
    }

    /** @param array<string, string> $form */
    private static function post(string $path, array $form): Request
    {
        return new Request(HttpMethodEnum::POST->value, $path, [], $form);
    }

    private function capture(callable $action): string
    {
        ob_start();
        $action();
        $output = ob_get_clean();
        self::assertIsString($output);

        return $output;
    }

    private function controller(UserRepositoryInterface $users): AuthController
    {
        $userAuthService = new UserAuthService($users);
        $tokens = $this->createStub(EmailVerificationTokenRepositoryInterface::class);
        $currentUser = new CurrentUser($this->session);

        return new AuthController(
            new RegisterUserService(
                $userAuthService,
                $tokens,
                $this->createStub(ConfirmationEmailQueueInterface::class),
            ),
            new LoginUserService($userAuthService, $currentUser),
            new ConfirmEmailService($tokens),
            $currentUser,
            new Responder(new CsrfToken($this->session)),
        );
    }
}
