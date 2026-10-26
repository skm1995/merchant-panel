<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class FailedMessageRetrier
{
    public function __construct(
        private TransportInterface $failedTransport,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Requeues every decodable failed message. Messages that cannot be decoded are left
     * unacknowledged, so they return to the failure queue for inspection.
     */
    public function retryAvailable(): FailedMessageRetryResult
    {
        $retried = 0;
        $skipped = 0;

        do {
            $fetched = 0;

            foreach ($this->failedTransport->get() as $envelope) {
                $fetched++;

                if ($envelope->getMessage() instanceof MessageDecodingFailedException) {
                    $skipped++;

                    continue;
                }

                $this->retry($envelope);
                $retried++;
            }
        } while ($fetched > 0);

        return new FailedMessageRetryResult($retried, $skipped);
    }

    private function retry(Envelope $envelope): void
    {
        $this->bus->dispatch($envelope->getMessage());
        $this->failedTransport->ack($envelope);
    }
}
