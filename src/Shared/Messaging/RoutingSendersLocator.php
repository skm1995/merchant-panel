<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Sends each message class to the transport named in the messaging configuration.
 */
final readonly class RoutingSendersLocator implements SendersLocatorInterface
{
    /** @param array<class-string, string> $routes transport names keyed by message class */
    public function __construct(
        private array $routes,
        private ContainerInterface $transports,
    ) {
    }

    /** @return iterable<string, SenderInterface> */
    public function getSenders(Envelope $envelope): iterable
    {
        $transportName = $this->routes[$envelope->getMessage()::class] ?? null;

        if ($transportName === null) {
            return;
        }

        $sender = $this->transports->get($transportName);

        if ($sender instanceof SenderInterface) {
            yield $transportName => $sender;
        }
    }
}
