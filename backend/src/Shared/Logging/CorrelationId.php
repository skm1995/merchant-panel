<?php

declare(strict_types=1);

namespace App\Shared\Logging;

/**
 * The id that ties together everything done for one HTTP request, including the messages it
 * queued and their handling in the worker. Every log record carries it.
 */
final class CorrelationId
{
    private ?string $id = null;

    public function get(): ?string
    {
        return $this->id;
    }

    public function set(string $id): void
    {
        $this->id = $id;
    }
}
