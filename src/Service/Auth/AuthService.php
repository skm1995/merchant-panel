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
    private const string VIEW_PATH_FORMAT = '%s/views/%s.php';
    private const string ERROR_DATA_KEY = 'error';
    private const string EMAIL_DATA_KEY = 'email';

    public function __construct(
        private readonly RegisterUserService $registerUser,
        private readonly LoginUserService $loginUser,
        private readonly LogoutUserService $logoutUser,
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
        require sprintf(self::VIEW_PATH_FORMAT, dirname(__DIR__, 3), $template);
    }
}
