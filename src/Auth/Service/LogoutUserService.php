<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Shared\Http\Session;

final class LogoutUserService
{
    private const string USER_EMAIL_SESSION_KEY = 'user_email';
    private const string HOME_ROUTE_PATH = '/';

    public function __construct(private readonly Session $session)
    {
    }

    public function execute(): AuthActionResult
    {
        $this->session->remove(self::USER_EMAIL_SESSION_KEY);

        return AuthActionResult::redirect(self::HOME_ROUTE_PATH);
    }
}
