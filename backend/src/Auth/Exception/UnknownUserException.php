<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class UnknownUserException extends AuthException
{
    private const string MESSAGE = 'No account uses this email address.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
