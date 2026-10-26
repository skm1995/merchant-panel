<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Shared\Database\MysqlError;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MysqlErrorTest extends TestCase
{
    private const string SQL_STATE = '23000';
    private const string INDEX = 'orders_idempotency_key_unique';
    private const string DUPLICATE_MESSAGE = "Duplicate entry 'k' for key 'orders.orders_idempotency_key_unique'";
    private const string OTHER_INDEX = 'users_email_unique';

    public function testRecognizesDuplicateKeyViolationsOfAnyOrOneIndex(): void
    {
        $exception = self::exception(1062, self::DUPLICATE_MESSAGE);

        self::assertTrue(MysqlError::isDuplicateKey($exception));
        self::assertTrue(MysqlError::isDuplicateKey($exception, self::INDEX));
        self::assertFalse(MysqlError::isDuplicateKey($exception, self::OTHER_INDEX));
        self::assertFalse(MysqlError::isDuplicateKey(self::exception(1213, self::DUPLICATE_MESSAGE)));
    }

    public function testRetriesOnlyDeadlocksAndLockWaitTimeouts(): void
    {
        self::assertTrue(MysqlError::isRetryable(self::exception(1213, self::DUPLICATE_MESSAGE)));
        self::assertTrue(MysqlError::isRetryable(self::exception(1205, self::DUPLICATE_MESSAGE)));
        self::assertFalse(MysqlError::isRetryable(self::exception(1062, self::DUPLICATE_MESSAGE)));
        self::assertFalse(MysqlError::isRetryable(new PDOException()));
    }

    private static function exception(int $code, string $message): PDOException
    {
        $exception = new PDOException($message);
        $exception->errorInfo = [self::SQL_STATE, $code, $message];

        return $exception;
    }
}
