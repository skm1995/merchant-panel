<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

final readonly class FailedMessageRetryResult
{
    public function __construct(public int $retried, public int $skipped)
    {
    }
}
