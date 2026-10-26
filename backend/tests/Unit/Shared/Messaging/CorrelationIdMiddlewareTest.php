<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging;

use App\Shared\Logging\CorrelationId;
use App\Shared\Messaging\CorrelationIdMiddleware;
use App\Shared\Messaging\CorrelationIdStamp;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

final class CorrelationIdMiddlewareTest extends TestCase
{
    private const string REQUEST_ID = 'worker-request-1';
    private const string TRANSPORT = 'emails';

    public function testRestoresTheRequestIdOfTheRequestThatQueuedTheMessage(): void
    {
        $envelope = new Envelope(new stdClass(), [new ReceivedStamp(self::TRANSPORT), new CorrelationIdStamp(self::REQUEST_ID)]);

        self::assertSame(self::REQUEST_ID, $this->idSeenByTheHandler($envelope));
    }

    public function testGivesAMessageWithoutOneANewId(): void
    {
        $envelope = new Envelope(new stdClass(), [new ReceivedStamp(self::TRANSPORT)]);

        self::assertTrue(Uuid::isValid((string) $this->idSeenByTheHandler($envelope)));
    }

    private function idSeenByTheHandler(Envelope $envelope): ?string
    {
        $correlation = new CorrelationId();
        $probe = new class ($correlation) implements MiddlewareInterface {
            public ?string $seen = null;

            public function __construct(private readonly CorrelationId $correlation)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->seen = $this->correlation->get();

                return $envelope;
            }
        };

        new MessageBus([new CorrelationIdMiddleware($correlation), $probe])->dispatch($envelope);

        return $probe->seen;
    }
}
