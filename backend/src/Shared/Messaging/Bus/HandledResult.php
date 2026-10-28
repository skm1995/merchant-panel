<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Sends a message through a synchronous Messenger bus and returns what its handler returned.
 * Messenger wraps a handler's exception in HandlerFailedException; the original is thrown
 * instead, so callers keep catching their own domain exceptions.
 */
final class HandledResult
{
    public static function of(MessageBusInterface $bus, object $message): mixed
    {
        try {
            $envelope = $bus->dispatch($message);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        return self::result($envelope);
    }

    private static function result(Envelope $envelope): mixed
    {
        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
