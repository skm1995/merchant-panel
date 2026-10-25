<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use DateTimeImmutable;

interface OutboxStoreInterface
{
    /**
     * Leases unclaimed or expired messages of the given types until the given time, skipping
     * rows other relays hold locked. Callers must run it inside a short transaction.
     *
     * @param non-empty-list<string> $messageTypes
     * @return list<ClaimedOutboxMessage>
     */
    public function claim(array $messageTypes, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array;

    public function remove(int $id): void;
}
