<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class WeakPasswordException extends AuthException
{
    private const string MESSAGE_FORMAT = 'Password must contain at least %d characters.';

    public function __construct(int $minimumLength)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $minimumLength));
    }
}
