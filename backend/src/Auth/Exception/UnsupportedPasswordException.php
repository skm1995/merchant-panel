<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/**
 * bcrypt hashes only the first 72 bytes of a password and cannot hash a NUL character, so
 * such passwords are refused instead of being cut short or failing.
 */
final class UnsupportedPasswordException extends AuthException
{
    private const string MESSAGE_FORMAT = 'Password must have at most %d bytes and no NUL characters.';

    public function __construct(int $maximumBytes)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $maximumBytes));
    }
}
