<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Logs every command the application handles with its duration, and the reason when its
 * handler refuses it, so the logs show what happened, not only which requests came in.
 */
final readonly class CommandLogMiddleware implements MiddlewareInterface
{
    private const string HANDLED_MESSAGE = 'Command handled';
    private const string FAILED_MESSAGE = 'Command failed';
    private const string COMMAND_KEY = 'command';
    private const string DURATION_KEY = 'duration_ms';
    private const string EXCEPTION_KEY = 'exception';
    private const int NANOSECONDS_PER_MILLISECOND = 1000000;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = [self::COMMAND_KEY => $envelope->getMessage()::class];
        $startedAt = hrtime(true);

        try {
            $handled = $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $exception) {
            $this->logger->warning(self::FAILED_MESSAGE, [...$context, self::EXCEPTION_KEY => $exception->getPrevious() ?? $exception]);

            throw $exception;
        }

        $this->logger->info(self::HANDLED_MESSAGE, [
            ...$context,
            self::DURATION_KEY => intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MILLISECOND),
        ]);

        return $handled;
    }
}
