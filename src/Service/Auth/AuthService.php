<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Enum\ContentTypeEnum;
use App\Enum\HeaderNameEnum;
use App\Enum\ResponseStatusEnum;

final class AuthService
{
    private const string REGISTER_TEMPLATE = 'auth/register';
    private const string LOGIN_TEMPLATE = 'auth/login';
    private const string CONFIRMATION_SENT_TEMPLATE = 'auth/confirmation-sent';
    private const string EMAIL_CONFIRMED_TEMPLATE = 'auth/email-confirmed';
    private const string CONFIRMATION_ERROR_TEMPLATE = 'auth/confirmation-error';
    private const string TOKEN_QUERY_KEY = 'token';
    private const string INVALID_TOKEN_INPUT = "\0";
    private const string VIEW_PATH_FORMAT = '%s/views/%s.php';
    private const string ERROR_DATA_KEY = 'error';
    private const string EMAIL_DATA_KEY = 'email';

    public function __construct(
        private readonly RegisterUserService $registerUser,
        private readonly LoginUserService $loginUser,
        private readonly LogoutUserService $logoutUser,
        private readonly ConfirmEmailService $confirmEmail,
    ) {
    }

    public function showRegister(): void
    {
        $this->render(self::REGISTER_TEMPLATE);
    }

    public function register(): void
    {
        $this->respond($this->registerUser->execute($_POST), self::REGISTER_TEMPLATE);
    }

    public function showLogin(): void
    {
        $this->render(self::LOGIN_TEMPLATE);
    }

    public function login(): void
    {
        $this->respond($this->loginUser->execute($_POST), self::LOGIN_TEMPLATE);
    }

    public function logout(): void
    {
        $this->respond($this->logoutUser->execute(), self::LOGIN_TEMPLATE);
    }

    public function showConfirmationSent(): void
    {
        $this->render(self::CONFIRMATION_SENT_TEMPLATE);
    }

    public function confirm(): void
    {
        $token = $_GET[self::TOKEN_QUERY_KEY] ?? null;
        $confirmationToken = is_string($token) ? $token : self::INVALID_TOKEN_INPUT;
        $result = $this->confirmEmail->execute($confirmationToken);
        $this->respond($result, self::CONFIRMATION_ERROR_TEMPLATE);
    }

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

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data = []): void
    {
        header(HeaderNameEnum::CONTENT_TYPE->value . ': ' . ContentTypeEnum::HTML->value);
        extract($data, EXTR_SKIP);
        $viewPath = dirname(__DIR__, 3) |> (
            static fn (string $projectRoot): string =>
            sprintf(self::VIEW_PATH_FORMAT, $projectRoot, $template)
        );
        require $viewPath;
    }
}
