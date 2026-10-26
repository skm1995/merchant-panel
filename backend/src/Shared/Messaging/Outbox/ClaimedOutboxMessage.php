<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use DateTimeImmutable;

final readonly class ClaimedOutboxMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $id,
        public string $messageType,
        public array $payload,
        public DateTimeImmutable $deliverAt,
        public ?string $correlationId,
    ) {
    }
}
