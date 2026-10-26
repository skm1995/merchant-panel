<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class InvalidEmailException extends AuthException
{
    private const string MESSAGE = 'Enter a valid email address.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
