<?php

declare(strict_types=1);

namespace App\Shared\Database;

use DateTimeInterface;

/**
 * Owns the format of DATETIME values sent to MySQL.
 */
final class SqlDateTime
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public static function format(DateTimeInterface $moment): string
    {
        return $moment->format(self::FORMAT);
    }
}
