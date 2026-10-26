<?php

declare(strict_types=1);

namespace App\Shared\Database;

final class SqlPlaceholders
{
    private const string PLACEHOLDER = '?';
    private const string SEPARATOR = ', ';

    /**
     * Positional placeholders for an IN (...) list of the given length.
     */
    public static function positional(int $count): string
    {
        return implode(self::SEPARATOR, array_fill(0, $count, self::PLACEHOLDER));
    }
}
