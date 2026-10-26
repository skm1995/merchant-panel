<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class ResendLimitReachedException extends AuthException
{
    private const string MESSAGE = 'You have reached the email confirmation resend limit. Try again later.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
