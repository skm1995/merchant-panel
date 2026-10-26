<?php

declare(strict_types=1);

namespace App\Shared\Database;

use PDOException;

/**
 * Reads MySQL driver error codes from PDO exceptions.
 */
final class MysqlError
{
    private const int DRIVER_ERROR_CODE_INDEX = 1;
    private const int DRIVER_MESSAGE_INDEX = 2;
    private const int DUPLICATE_KEY = 1062;
    private const int DEADLOCK = 1213;
    private const int LOCK_WAIT_TIMEOUT = 1205;

    /**
     * Whether the statement violated a unique index; with $index, only that index counts.
     */
    public static function isDuplicateKey(PDOException $exception, ?string $index = null): bool
    {
        return self::code($exception) === self::DUPLICATE_KEY
            && ($index === null || str_contains((string) ($exception->errorInfo[self::DRIVER_MESSAGE_INDEX] ?? null), $index));
    }

    /**
     * Whether MySQL rolled back or abandoned the transaction in a way that retrying it can fix.
     */
    public static function isRetryable(PDOException $exception): bool
    {
        return in_array(self::code($exception), [self::DEADLOCK, self::LOCK_WAIT_TIMEOUT], true);
    }

    private static function code(PDOException $exception): mixed
    {
        return $exception->errorInfo[self::DRIVER_ERROR_CODE_INDEX] ?? null;
    }
}
