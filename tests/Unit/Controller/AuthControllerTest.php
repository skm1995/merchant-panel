<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AuthController;
use App\Repository\UserRepositoryInterface;
use App\Service\Auth\AuthService as AuthApplicationService;
use App\Service\Auth\LoginUserService;
use App\Service\Auth\LogoutUserService;
use App\Service\Auth\RegisterUserService;
use App\Service\Auth\UserAuthService;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private const string EMAIL_FIELD = 'email';
    private const string PASSWORD_FIELD = 'password';
    private const string INVALID_EMAIL = 'invalid';
    private const string PASSWORD_VALUE = 'long-enough-password';
    private const string VALIDATION_ERROR = 'Enter a valid email address.';

    public function testRegistrationDisplaysValidationError(): void
    {
        $originalPost = $_POST;
        $_POST = [
            self::EMAIL_FIELD => self::INVALID_EMAIL,
            self::PASSWORD_FIELD => self::PASSWORD_VALUE,
        ];
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('create');
        $controller = self::controller($users);
        ob_start();

        $controller->register();
        $output = ob_get_clean();
        $_POST = $originalPost;

        self::assertIsString($output);
        self::assertStringContainsString(self::VALIDATION_ERROR, $output);
        self::assertStringContainsString(self::INVALID_EMAIL, $output);
    }

    public function testLoginPageRendersForm(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $controller = self::controller($users);
        ob_start();

        $controller->showLogin();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('action="/login"', $output);
    }

    private static function controller(UserRepositoryInterface $users): AuthController
    {
        $authService = new UserAuthService($users);

        return new AuthController(new AuthApplicationService(
            new RegisterUserService($authService),
            new LoginUserService($authService),
            new LogoutUserService(),
        ));
    }
}
