<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Enum\PaymentProviderEnum;

/**
 * Inbox of processed provider events, used to process each webhook delivery once.
 */
interface WebhookInboxRepositoryInterface
{
    /**
     * Records the event; returns false when it was already recorded.
     */
    public function recordIfNew(PaymentProviderEnum $provider, string $eventId): bool;
}
