<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\CurrentUser;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\RegisterUserService;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;
use DomainException;

final class AuthController
{
    private const string REGISTER_ROUTE_PATH = '/register';
    private const string LOGIN_ROUTE_PATH = '/login';
    private const string LOGOUT_ROUTE_PATH = '/logout';
    private const string CONFIRMATION_SENT_ROUTE_PATH = '/confirmation-sent';
    private const string CONFIRM_ROUTE_PATH = '/confirm';
    private const string EMAIL_CONFIRMED_ROUTE_PATH = '/email-confirmed';
    private const string REGISTER_TEMPLATE = 'auth/register';
    private const string LOGIN_TEMPLATE = 'auth/login';
    private const string CONFIRMATION_SENT_TEMPLATE = 'auth/confirmation-sent';
    private const string EMAIL_CONFIRMED_TEMPLATE = 'auth/email-confirmed';
    private const string CONFIRMATION_TEMPLATE = 'auth/confirmation';
    private const string CONFIRMATION_ERROR_TEMPLATE = 'auth/confirmation-error';
    private const string EMAIL_FIELD = 'email';
    private const string PASSWORD_FIELD = 'password';
    private const string TOKEN_FIELD = 'token';
    private const string ERROR_DATA_KEY = 'error';
    private const string EMAIL_DATA_KEY = 'email';
    private const string TOKEN_DATA_KEY = 'token';

    public function __construct(
        private readonly RegisterUserService $registerUser,
        private readonly LoginUserService $loginUser,
        private readonly ConfirmEmailService $confirmEmail,
        private readonly CurrentUser $currentUser,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::REGISTER_ROUTE_PATH)]
    public function showRegister(): void
    {
        $this->responder->render(self::REGISTER_TEMPLATE);
    }

    #[Route(HttpMethodEnum::POST, self::REGISTER_ROUTE_PATH)]
    public function register(Request $request): void
    {
        $this->submitCredentials(
            $request,
            $this->registerUser->execute(...),
            self::REGISTER_TEMPLATE,
            self::CONFIRMATION_SENT_ROUTE_PATH,
        );
    }

    #[Route(HttpMethodEnum::GET, self::LOGIN_ROUTE_PATH)]
    public function showLogin(): void
    {
        $this->responder->render(self::LOGIN_TEMPLATE);
    }

    #[Route(HttpMethodEnum::POST, self::LOGIN_ROUTE_PATH)]
    public function login(Request $request): void
    {
        $this->submitCredentials($request, $this->loginUser->execute(...), self::LOGIN_TEMPLATE, Request::ROOT_PATH);
    }

    #[Route(HttpMethodEnum::POST, self::LOGOUT_ROUTE_PATH)]
    public function logout(): void
    {
        $this->currentUser->signOut();
        $this->responder->redirect(Request::ROOT_PATH);
    }

    #[Route(HttpMethodEnum::GET, self::CONFIRMATION_SENT_ROUTE_PATH)]
    public function showConfirmationSent(): void
    {
        $this->responder->render(self::CONFIRMATION_SENT_TEMPLATE);
    }

    #[Route(HttpMethodEnum::GET, self::CONFIRM_ROUTE_PATH)]
    public function confirm(Request $request): void
    {
        $token = $request->query(self::TOKEN_FIELD);

        if ($token === null) {
            $this->responder->status(ResponseStatusEnum::BAD_REQUEST);

            return;
        }

        $this->responder->render(self::CONFIRMATION_TEMPLATE, [self::TOKEN_DATA_KEY => $token]);
    }

    #[Route(HttpMethodEnum::POST, self::CONFIRM_ROUTE_PATH)]
    public function confirmPost(Request $request): void
    {
        $token = $request->post(self::TOKEN_FIELD);

        if ($token === null) {
            $this->responder->status(ResponseStatusEnum::BAD_REQUEST);

            return;
        }

        try {
            $this->confirmEmail->execute($token);
        } catch (DomainException $exception) {
            $this->responder->render(self::CONFIRMATION_ERROR_TEMPLATE, [
                self::ERROR_DATA_KEY => $exception->getMessage(),
            ]);

            return;
        }

        $this->responder->redirect(self::EMAIL_CONFIRMED_ROUTE_PATH);
    }

    #[Route(HttpMethodEnum::GET, self::EMAIL_CONFIRMED_ROUTE_PATH)]
    public function showEmailConfirmed(): void
    {
        $this->responder->render(self::EMAIL_CONFIRMED_TEMPLATE);
    }

    /** @param callable(string, string): void $action */
    private function submitCredentials(
        Request $request,
        callable $action,
        string $template,
        string $successPath,
    ): void {
        $email = $request->post(self::EMAIL_FIELD);
        $password = $request->post(self::PASSWORD_FIELD);

        if ($email === null || $password === null) {
            $this->responder->status(ResponseStatusEnum::BAD_REQUEST);

            return;
        }

        try {
            $action($email, $password);
        } catch (DomainException $exception) {
            $this->responder->render($template, [
                self::ERROR_DATA_KEY => $exception->getMessage(),
                self::EMAIL_DATA_KEY => $email,
            ]);

            return;
        }

        $this->responder->redirect($successPath);
    }
}
