<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class FailedMessageRetrier
{
    public function __construct(
        private readonly TransportInterface $failedTransport,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function retryAvailable(): int
    {
        $retried = 0;

        foreach ($this->failedTransport->get() as $envelope) {
            $this->retry($envelope);
            $retried++;
        }

        return $retried;
    }

    private function retry(Envelope $envelope): void
    {
        $this->bus->dispatch($envelope->getMessage());
        $this->failedTransport->ack($envelope);
    }
}
