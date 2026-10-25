<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Shared\Config\Environment;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

final class AuthMessengerRuntime
{
    public const string TRANSPORT_NAME = 'emails';
    private const string TRANSPORT_DSN_ENV = 'MESSENGER_TRANSPORT_DSN';
    private const string FAILURE_TRANSPORT_DSN_ENV = 'MESSENGER_FAILURE_TRANSPORT_DSN';
    private const string MAILER_DSN_ENV = 'MAILER_DSN';
    private const string APP_BASE_URL_ENV = 'APP_BASE_URL';
    private const string MAILER_FROM_ENV = 'MAILER_FROM';

    public static function createQueueFromEnvironment(): ConfirmationEmailQueueInterface
    {
        $transport = self::createTransport(self::TRANSPORT_DSN_ENV);
        $bus = self::createBus($transport);

        return new SymfonyMessengerEmailQueue($bus);
    }

    public static function createWorkerFromEnvironment(): Worker
    {
        $transport = self::createTransport(self::TRANSPORT_DSN_ENV);
        $failureTransport = self::createTransport(self::FAILURE_TRANSPORT_DSN_ENV);
        $mailerTransport = Environment::get(self::MAILER_DSN_ENV)
            |> Transport::fromDsn(...);
        $mailer = new Mailer($mailerTransport);
        $handler = new SendConfirmationEmailHandler(
            $mailer,
            Environment::get(self::APP_BASE_URL_ENV),
            Environment::get(self::MAILER_FROM_ENV),
        );
        $eventDispatcher = self::createEventDispatcher($transport, $failureTransport);
        $bus = self::createBus($transport, $handler);

        return new Worker([self::TRANSPORT_NAME => $transport], $bus, $eventDispatcher);
    }

    public static function retryFailedMessagesFromEnvironment(): FailedMessageRetryResult
    {
        $failedTransport = self::createTransport(self::FAILURE_TRANSPORT_DSN_ENV);
        $emailTransport = self::createTransport(self::TRANSPORT_DSN_ENV);

        return new FailedMessageRetrier($failedTransport, self::createBus($emailTransport))->retryAvailable();
    }

    private static function createTransport(string $environmentKey): TransportInterface
    {
        $factory = new AmqpTransportFactory();

        return $factory->createTransport(
            Environment::get($environmentKey),
            [],
            new PhpSerializer(),
        );
    }

    private static function createBus(
        TransportInterface $transport,
        ?SendConfirmationEmailHandler $handler = null,
    ): MessageBus {
        $handlers = $handler === null ? [] : [ConfirmationEmailMessage::class => [$handler]];

        return new MessageBus([
            new SendMessageMiddleware(new AuthMessageSendersLocator($transport)),
            new HandleMessageMiddleware(new HandlersLocator($handlers)),
        ]);
    }

    private static function createEventDispatcher(
        TransportInterface $transport,
        TransportInterface $failureTransport,
    ): EventDispatcher {
        $senders = new RuntimeServiceContainer([self::TRANSPORT_NAME => $transport]);
        $retryStrategies = new RuntimeServiceContainer([self::TRANSPORT_NAME => new MultiplierRetryStrategy()]);
        $failureSenders = new RuntimeServiceContainer([self::TRANSPORT_NAME => $failureTransport]);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $retryStrategies));
        $eventDispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($failureSenders));

        return $eventDispatcher;
    }
}
