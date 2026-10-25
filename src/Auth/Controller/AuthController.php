<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Service\AuthActionResult;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\LogoutUserService;
use App\Auth\Service\RegisterUserService;
use App\Shared\Http\Controller;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Route;

final class AuthController extends Controller
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
    private const string TOKEN_KEY = 'token';
    private const string INVALID_TOKEN_INPUT = "\0";
    private const string ERROR_DATA_KEY = 'error';
    private const string EMAIL_DATA_KEY = 'email';

    public function __construct(
        private readonly RegisterUserService $registerUser,
        private readonly LoginUserService $loginUser,
        private readonly LogoutUserService $logoutUser,
        private readonly ConfirmEmailService $confirmEmail,
        CsrfToken $csrfToken,
    ) {
        parent::__construct($csrfToken);
    }

    #[Route(HttpMethodEnum::GET, self::REGISTER_ROUTE_PATH)]
    public function showRegister(): void
    {
        $this->render(self::REGISTER_TEMPLATE);
    }

    #[Route(HttpMethodEnum::POST, self::REGISTER_ROUTE_PATH)]
    public function register(): void
    {
        $this->respond($this->registerUser->execute($_POST), self::REGISTER_TEMPLATE);
    }

    #[Route(HttpMethodEnum::GET, self::LOGIN_ROUTE_PATH)]
    public function showLogin(): void
    {
        $this->render(self::LOGIN_TEMPLATE);
    }

    #[Route(HttpMethodEnum::POST, self::LOGIN_ROUTE_PATH)]
    public function login(): void
    {
        $this->respond($this->loginUser->execute($_POST), self::LOGIN_TEMPLATE);
    }

    #[Route(HttpMethodEnum::POST, self::LOGOUT_ROUTE_PATH)]
    public function logout(): void
    {
        $this->respond($this->logoutUser->execute(), self::LOGIN_TEMPLATE);
    }

    #[Route(HttpMethodEnum::GET, self::CONFIRMATION_SENT_ROUTE_PATH)]
    public function showConfirmationSent(): void
    {
        $this->render(self::CONFIRMATION_SENT_TEMPLATE);
    }

    #[Route(HttpMethodEnum::GET, self::CONFIRM_ROUTE_PATH)]
    public function confirm(): void
    {
        $token = $_GET[self::TOKEN_KEY] ?? null;
        $this->render(self::CONFIRMATION_TEMPLATE, [
            self::TOKEN_KEY => is_string($token) ? $token : self::INVALID_TOKEN_INPUT,
        ]);
    }

    #[Route(HttpMethodEnum::POST, self::CONFIRM_ROUTE_PATH)]
    public function confirmPost(): void
    {
        $token = $_POST[self::TOKEN_KEY] ?? null;
        $confirmationToken = is_string($token) ? $token : self::INVALID_TOKEN_INPUT;
        $this->respond($this->confirmEmail->execute($confirmationToken), self::CONFIRMATION_ERROR_TEMPLATE);
    }

    #[Route(HttpMethodEnum::GET, self::EMAIL_CONFIRMED_ROUTE_PATH)]
    public function showEmailConfirmed(): void
    {
        $this->render(self::EMAIL_CONFIRMED_TEMPLATE);
    }

    private function respond(AuthActionResult $result, string $template): void
    {
        if ($result->redirectPath !== null) {
            header(
                HeaderNameEnum::LOCATION->value . ': ' . $result->redirectPath,
                true,
                ResponseStatusEnum::SEE_OTHER->value,
            );
            return;
        }

        $this->render($template, [
            self::ERROR_DATA_KEY => $result->errorMessage,
            self::EMAIL_DATA_KEY => $result->email,
        ]);
    }
}
