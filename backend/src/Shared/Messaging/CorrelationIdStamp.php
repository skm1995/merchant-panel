<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the request id of the request that queued a message to the worker.
 */
final readonly class CorrelationIdStamp implements StampInterface
{
    public function __construct(public string $id)
    {
    }
}
