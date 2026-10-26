<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class InvalidConfirmationTokenException extends AuthException
{
    private const string MESSAGE = 'This confirmation link is invalid or has expired.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
