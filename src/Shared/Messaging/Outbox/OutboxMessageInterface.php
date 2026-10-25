<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Contract\Arrayable;

/**
 * Message stored in the transactional outbox. The type name is a persisted contract and
 * must stay stable even when the class is renamed or moved.
 */
interface OutboxMessageInterface extends Arrayable
{
    public static function messageType(): string;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static;
}
