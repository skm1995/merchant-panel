<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Messenger;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Infrastructure\Messenger\FailedMessageRetrier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class FailedMessageRetrierTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string SECOND_EMAIL = 'second@example.com';
    private const string TOKEN = 'email-confirmation-token';
    private const string DECODING_FAILURE = 'Message class not found during decoding.';

    public function testDispatchesFailedMessagesAgainAndAcknowledgesThem(): void
    {
        $envelope = new Envelope(new ConfirmationEmailMessage(self::EMAIL, self::TOKEN), [
            new RedeliveryStamp(3),
            new SentToFailureTransportStamp('emails'),
        ]);
        $failedTransport = $this->createMock(TransportInterface::class);
        $failedTransport->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls([$envelope], []);
        $failedTransport->expects(self::once())->method('ack')->with($envelope);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(
            self::callback(
                static fn (object $message): bool =>
                $message instanceof ConfirmationEmailMessage
                && $message->email === self::EMAIL
                && $message->token === self::TOKEN
            ),
        )->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $result = new FailedMessageRetrier($failedTransport, $bus)->retryAvailable();

        self::assertSame(1, $result->retried);
        self::assertSame(0, $result->skipped);
    }

    public function testRetriesEveryMessageWhenTransportReturnsOneMessagePerFetch(): void
    {
        $first = new Envelope(new ConfirmationEmailMessage(self::EMAIL, self::TOKEN));
        $second = new Envelope(new ConfirmationEmailMessage(self::SECOND_EMAIL, self::TOKEN));
        $failedTransport = $this->createMock(TransportInterface::class);
        $failedTransport->expects(self::exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls([$first], [$second], []);
        $failedTransport->expects(self::exactly(2))->method('ack');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $result = new FailedMessageRetrier($failedTransport, $bus)->retryAvailable();

        self::assertSame(2, $result->retried);
    }

    public function testKeepsUndecodableMessagesInspectableAndRetriesTheRest(): void
    {
        $undecodable = new Envelope(new MessageDecodingFailedException(self::DECODING_FAILURE));
        $decodable = new Envelope(new ConfirmationEmailMessage(self::EMAIL, self::TOKEN));
        $failedTransport = $this->createMock(TransportInterface::class);
        $failedTransport->expects(self::exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls([$undecodable], [$decodable], []);
        $failedTransport->expects(self::once())->method('ack')->with($decodable);
        $failedTransport->expects(self::never())->method('reject');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ConfirmationEmailMessage::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $result = new FailedMessageRetrier($failedTransport, $bus)->retryAvailable();

        self::assertSame(1, $result->retried);
        self::assertSame(1, $result->skipped);
    }

    public function testKeepsFailedMessageWhenRedispatchFails(): void
    {
        $envelope = new Envelope(new ConfirmationEmailMessage(self::EMAIL, self::TOKEN));
        $failedTransport = $this->createMock(TransportInterface::class);
        $failedTransport->expects(self::once())->method('get')->willReturn([$envelope]);
        $failedTransport->expects(self::never())->method('ack');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willThrowException(new \RuntimeException('Broker unavailable.'));

        try {
            new FailedMessageRetrier($failedTransport, $bus)->retryAvailable();
            self::fail('Expected the broker failure to be propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Broker unavailable.', $exception->getMessage());
        }
    }
}
