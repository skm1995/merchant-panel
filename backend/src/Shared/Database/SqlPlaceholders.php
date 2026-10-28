<?php

declare(strict_types=1);

namespace App\Shared\Database;

final class SqlPlaceholders
{
    private const string PLACEHOLDER = '?';
    private const string SEPARATOR = ', ';
    private const string ROW_FORMAT = '(%s)';

    /**
     * Positional placeholders for an IN (...) list of the given length.
     */
    public static function positional(int $count): string
    {
        return implode(self::SEPARATOR, array_fill(0, $count, self::PLACEHOLDER));
    }

    /**
     * Placeholder groups for a multi-row INSERT ... VALUES, one "(?, ?)" per row.
     */
    public static function rows(int $rowCount, int $columnCount): string
    {
        return implode(self::SEPARATOR, array_fill(0, $rowCount, sprintf(self::ROW_FORMAT, self::positional($columnCount))));
    }
}
