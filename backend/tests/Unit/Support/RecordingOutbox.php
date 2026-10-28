<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;
use DateTimeImmutable;

/**
 * Keeps the messages a test's code adds to the outbox, so the test can check what was
 * recorded instead of which calls were made.
 */
final class RecordingOutbox implements OutboxInterface
{
    /** @var list<array{message: OutboxMessageInterface, deliverAt: DateTimeImmutable}> */
    public array $recorded = [];

    public function add(OutboxMessageInterface $message, DateTimeImmutable $deliverAt): void
    {
        $this->recorded[] = ['message' => $message, 'deliverAt' => $deliverAt];
    }

    /** @return list<OutboxMessageInterface> */
    public function messages(): array
    {
        return array_column($this->recorded, 'message');
    }
}
