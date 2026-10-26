<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Shared\Database\Enum\IsolationLevelEnum;
use PDO;
use PDOException;
use Throwable;

/**
 * Runs an operation in one database transaction at the requested isolation level.
 *
 * MySQL rolls back the whole transaction on a deadlock, and a lock wait timeout leaves
 * it in an unknown partial state, so both are rolled back and the whole operation is
 * retried a bounded number of times with a growing delay. Operations must therefore be
 * free of side effects outside the database.
 */
final class PdoTransactionRunner implements TransactionRunnerInterface
{
    public const int MAXIMUM_ATTEMPTS = 3;
    private const int DEFAULT_RETRY_DELAY_MICROSECONDS = 20000;
    private const string ISOLATION_LEVEL_STATEMENT_FORMAT = 'SET TRANSACTION ISOLATION LEVEL %s';

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $retryDelayMicroseconds = self::DEFAULT_RETRY_DELAY_MICROSECONDS,
    ) {
    }

    public function run(
        callable $operation,
        IsolationLevelEnum $isolationLevel = IsolationLevelEnum::REPEATABLE_READ,
    ): mixed {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt($operation, $isolationLevel);
            } catch (PDOException $exception) {
                if (!MysqlError::isRetryable($exception) || $attempt >= self::MAXIMUM_ATTEMPTS) {
                    throw $exception;
                }

                usleep($this->retryDelayMicroseconds * $attempt);
            }
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function attempt(callable $operation, IsolationLevelEnum $isolationLevel): mixed
    {
        $this->pdo->exec(sprintf(self::ISOLATION_LEVEL_STATEMENT_FORMAT, $isolationLevel->value));
        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
