<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunner;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionRunnerTest extends TestCase
{
    private const string RESULT = 'committed';
    private const string FAILURE = 'Business rule violated.';
    private const string READ_COMMITTED_STATEMENT = 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED';
    private const int DEADLOCK_ERROR = 1213;
    private const int LOCK_WAIT_TIMEOUT_ERROR = 1205;
    private const int SYNTAX_ERROR = 1064;
    private const int NO_DELAY = 0;

    public function testCommitsAndReturnsTheOperationResultAtTheRequestedIsolationLevel(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->with(self::READ_COMMITTED_STATEMENT);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::once())->method('commit');
        $pdo->expects(self::never())->method('rollBack');

        $result = self::runner($pdo)->run(static fn (): string => self::RESULT, IsolationLevelEnum::READ_COMMITTED);

        self::assertSame(self::RESULT, $result);
    }

    public function testRetriesTheWholeOperationAfterDeadlockOrLockWaitTimeout(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects(self::exactly(3))->method('beginTransaction');
        $pdo->expects(self::exactly(2))->method('rollBack');
        $pdo->expects(self::once())->method('commit');
        $failures = [
            self::driverException(self::DEADLOCK_ERROR),
            self::driverException(self::LOCK_WAIT_TIMEOUT_ERROR),
        ];

        $result = self::runner($pdo)->run(static function () use (&$failures): string {
            $failure = array_shift($failures);

            return $failure === null ? self::RESULT : throw $failure;
        });

        self::assertSame(self::RESULT, $result);
        self::assertSame([], $failures);
    }

    public function testDoesNotRetryOtherFailures(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::once())->method('rollBack');

        $this->expectException(RuntimeException::class);
        self::runner($pdo)->run(static fn (): never => throw new RuntimeException(self::FAILURE));
    }

    public function testDoesNotRetryNonDeadlockDatabaseErrors(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects(self::once())->method('beginTransaction');

        $this->expectException(PDOException::class);
        self::runner($pdo)->run(static fn (): never => throw self::driverException(self::SYNTAX_ERROR));
    }

    public function testGivesUpAfterTheMaximumNumberOfAttempts(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects(self::exactly(TransactionRunner::MAXIMUM_ATTEMPTS))->method('beginTransaction');

        $this->expectException(PDOException::class);
        self::runner($pdo)->run(static fn (): never => throw self::driverException(self::DEADLOCK_ERROR));
    }

    private static function runner(PDO $pdo): TransactionRunner
    {
        return new TransactionRunner($pdo, self::NO_DELAY);
    }

    private static function driverException(int $driverCode): PDOException
    {
        $exception = new PDOException(self::FAILURE);
        $exception->errorInfo = ['40001', $driverCode, self::FAILURE];

        return $exception;
    }
}
