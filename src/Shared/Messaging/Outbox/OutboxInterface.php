<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use DateTimeImmutable;

interface OutboxInterface
{
    /**
     * Stores a message for publishing at the given time. Call it inside the transaction that
     * changes the data the message describes, so both are committed or rolled back together.
     */
    public function add(OutboxMessageInterface $message, DateTimeImmutable $deliverAt): void;
}
