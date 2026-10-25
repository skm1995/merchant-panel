<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class LogoutUserService
{
    private const string USER_EMAIL_SESSION_KEY = 'user_email';
    private const string HOME_ROUTE_PATH = '/';

    public function execute(): AuthActionResult
    {
        unset($_SESSION[self::USER_EMAIL_SESSION_KEY]);

        return AuthActionResult::redirect(self::HOME_ROUTE_PATH);
    }
}
