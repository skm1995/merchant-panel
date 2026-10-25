<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Messenger;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Infrastructure\Messenger\SymfonyMessengerEmailQueue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SymfonyMessengerEmailQueueTest extends TestCase
{
    private const string RECIPIENT = 'person@example.com';
    private const string TOKEN = 'confirmation-token';

    public function testDispatchPassesConfirmationMessageToMessengerBus(): void
    {
        $message = new ConfirmationEmailMessage(self::RECIPIENT, self::TOKEN);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($message)->willReturn(new Envelope($message));

        new SymfonyMessengerEmailQueue($bus)->dispatch($message);
    }
}
