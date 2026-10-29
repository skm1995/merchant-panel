<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class InvalidResetTokenException extends AuthException
{
    private const string MESSAGE = 'This reset link is invalid or has expired. Ask for a new one.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
