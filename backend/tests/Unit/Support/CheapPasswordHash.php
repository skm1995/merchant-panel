<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

/**
 * Hashes test passwords with the lowest bcrypt cost: verifying them is as correct as with
 * the production cost but takes a millisecond instead of a fifth of a second.
 */
final class CheapPasswordHash
{
    private const array OPTIONS = ['cost' => 4];

    public static function of(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, self::OPTIONS);
    }
}
