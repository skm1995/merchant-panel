<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;

/**
 * Runs operations directly so unit tests can exercise services that use transactions.
 */
final class ImmediateTransactionRunner implements TransactionRunnerInterface
{
    public ?IsolationLevelEnum $lastIsolationLevel = null;

    public function run(
        callable $operation,
        IsolationLevelEnum $isolationLevel = IsolationLevelEnum::REPEATABLE_READ,
    ): mixed {
        $this->lastIsolationLevel = $isolationLevel;

        return $operation();
    }
}
