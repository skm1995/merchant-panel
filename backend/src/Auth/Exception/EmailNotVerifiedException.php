<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class EmailNotVerifiedException extends AuthException
{
    private const string MESSAGE = 'Confirm your email address before logging in.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
