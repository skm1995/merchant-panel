<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

/**
 * Logs every message the worker handles, with its duration, and every failure before
 * Messenger retries it.
 */
final readonly class MessageLogMiddleware implements MiddlewareInterface
{
    private const string HANDLED_MESSAGE = 'Message handled';
    private const string FAILED_MESSAGE = 'Message failed';
    private const string MESSAGE_TYPE_KEY = 'message_type';
    private const string TRANSPORT_KEY = 'transport';
    private const string DURATION_KEY = 'duration_ms';
    private const string EXCEPTION_KEY = 'exception';
    private const int NANOSECONDS_PER_MILLISECOND = 1000000;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $received = $envelope->last(ReceivedStamp::class);

        if ($received === null) {
            return $stack->next()->handle($envelope, $stack);
        }

        $context = [
            self::MESSAGE_TYPE_KEY => $envelope->getMessage()::class,
            self::TRANSPORT_KEY => $received->getTransportName(),
        ];
        $startedAt = hrtime(true);

        try {
            $handled = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $exception) {
            $this->logger->error(self::FAILED_MESSAGE, [...$context, self::EXCEPTION_KEY => $exception]);

            throw $exception;
        }

        $this->logger->info(self::HANDLED_MESSAGE, [
            ...$context,
            self::DURATION_KEY => intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MILLISECOND),
        ]);

        return $handled;
    }
}
