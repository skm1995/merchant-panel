<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class InvalidCredentialsException extends AuthException
{
    private const string MESSAGE = 'Invalid email or password.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
