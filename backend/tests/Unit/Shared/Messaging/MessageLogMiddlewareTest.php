<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging;

use App\Shared\Messaging\MessageLogMiddleware;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class MessageLogMiddlewareTest extends TestCase
{
    private const string TRANSPORT = 'emails';

    public function testLogsEachHandledMessage(): void
    {
        $log = new TestHandler();

        new MessageBus([new MessageLogMiddleware(new Logger('test', [$log]))])->dispatch(self::received());

        $record = $log->getRecords()[0] ?? null;
        self::assertNotNull($record);
        self::assertSame(Level::Info, $record->level);
        self::assertSame(stdClass::class, $record->context['message_type']);
        self::assertSame(self::TRANSPORT, $record->context['transport']);
    }

    public function testLogsAFailureAndLetsMessengerRetryIt(): void
    {
        $log = new TestHandler();
        $failing = new class () implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw new RuntimeException('Mailer unavailable');
            }
        };

        try {
            new MessageBus([new MessageLogMiddleware(new Logger('test', [$log])), $failing])->dispatch(self::received());
            self::fail('The failure must reach Messenger so it can retry.');
        } catch (RuntimeException) {
            self::assertTrue($log->hasErrorThatContains('Message failed'));
        }
    }

    public function testIgnoresMessagesBeingSent(): void
    {
        $log = new TestHandler();

        new MessageBus([new MessageLogMiddleware(new Logger('test', [$log]))])->dispatch(new Envelope(new stdClass()));

        self::assertSame([], $log->getRecords());
    }

    private static function received(): Envelope
    {
        return new Envelope(new stdClass(), [new ReceivedStamp(self::TRANSPORT)]);
    }
}
