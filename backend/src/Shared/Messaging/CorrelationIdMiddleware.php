<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use App\Shared\Logging\CorrelationId;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Before the worker handles a message, restores the request id it was queued under, so its
 * logs line up with the request's; a message without one gets a new id.
 */
final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(private CorrelationId $correlation)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(ReceivedStamp::class) !== null) {
            $stamp = $envelope->last(CorrelationIdStamp::class);
            $this->correlation->set($stamp === null ? Uuid::v4()->toRfc4122() : $stamp->id);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
