<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Shared\Database\Enum\IsolationLevelEnum;

interface TransactionRunnerInterface
{
    /**
     * Runs the operation in one transaction, retrying it as a whole after deadlocks.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function run(
        callable $operation,
        IsolationLevelEnum $isolationLevel = IsolationLevelEnum::REPEATABLE_READ,
    ): mixed;
}
