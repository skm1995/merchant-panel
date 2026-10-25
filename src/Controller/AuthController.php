<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Auth\AuthService;

final class AuthController
{
    public const string REGISTER_ROUTE_PATH = '/register';
    public const string LOGIN_ROUTE_PATH = '/login';
    public const string LOGOUT_ROUTE_PATH = '/logout';
    public const string CONFIRMATION_SENT_ROUTE_PATH = '/confirmation-sent';
    public const string CONFIRM_ROUTE_PATH = '/confirm';
    public const string EMAIL_CONFIRMED_ROUTE_PATH = '/email-confirmed';
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showRegister(): void
    {
        $this->authService->showRegister();
    }

    public function register(): void
    {
        $this->authService->register();
    }

    public function showLogin(): void
    {
        $this->authService->showLogin();
    }

    public function login(): void
    {
        $this->authService->login();
    }

    public function logout(): void
    {
        $this->authService->logout();
    }

    public function showConfirmationSent(): void
    {
        $this->authService->showConfirmationSent();
    }

    public function confirm(): void
    {
        $this->authService->confirm();
    }

    public function showEmailConfirmed(): void
    {
        $this->authService->showEmailConfirmed();
    }
}
