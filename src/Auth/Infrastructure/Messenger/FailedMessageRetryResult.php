<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

final readonly class FailedMessageRetryResult
{
    public function __construct(public int $retried, public int $skipped)
    {
    }
}
