<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Message\ConfirmationEmailQueueInterface;
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
    private const string TRANSPORT_DSN_ENV = 'MESSENGER_TRANSPORT_DSN';
    private const string FAILURE_TRANSPORT_DSN_ENV = 'MESSENGER_FAILURE_TRANSPORT_DSN';
    private const string MAILER_DSN_ENV = 'MAILER_DSN';
    private const string APP_BASE_URL_ENV = 'APP_BASE_URL';
    private const string MAILER_FROM_ENV = 'MAILER_FROM';
    private const string DEFAULT_TRANSPORT_DSN = 'amqp://app:app@rabbitmq:5672/%2f/emails';
    private const string DEFAULT_FAILURE_TRANSPORT_DSN = 'amqp://app:app@rabbitmq:5672/%2f/emails_failed';
    private const string DEFAULT_MAILER_DSN = 'smtp://mailhog:1025';
    private const string DEFAULT_APP_BASE_URL = 'http://localhost:8080';
    private const string DEFAULT_MAILER_FROM = 'no-reply@example.com';

    public static function createQueueFromEnvironment(): ConfirmationEmailQueueInterface
    {
        $transport = self::createTransport(self::TRANSPORT_DSN_ENV, self::DEFAULT_TRANSPORT_DSN);
        $bus = self::createBus($transport);

        return new SymfonyMessengerEmailQueue($bus);
    }

    public static function createWorkerFromEnvironment(): Worker
    {
        $transport = self::createTransport(self::TRANSPORT_DSN_ENV, self::DEFAULT_TRANSPORT_DSN);
        $failureTransport = self::createTransport(
            self::FAILURE_TRANSPORT_DSN_ENV,
            self::DEFAULT_FAILURE_TRANSPORT_DSN,
        );
        $mailerTransport = self::environment(self::MAILER_DSN_ENV, self::DEFAULT_MAILER_DSN)
            |> Transport::fromDsn(...);
        $mailer = new Mailer($mailerTransport);
        $handler = new SendConfirmationEmailHandler(
            $mailer,
            self::environment(self::APP_BASE_URL_ENV, self::DEFAULT_APP_BASE_URL),
            self::environment(self::MAILER_FROM_ENV, self::DEFAULT_MAILER_FROM),
        );
        $eventDispatcher = self::createEventDispatcher($transport, $failureTransport);
        $bus = self::createBus($transport, $handler);

        return new Worker(['emails' => $transport], $bus, $eventDispatcher);
    }

    public static function retryFailedMessagesFromEnvironment(): int
    {
        $failedTransport = self::createTransport(
            self::FAILURE_TRANSPORT_DSN_ENV,
            self::DEFAULT_FAILURE_TRANSPORT_DSN,
        );
        $emailTransport = self::createTransport(self::TRANSPORT_DSN_ENV, self::DEFAULT_TRANSPORT_DSN);

        return new FailedMessageRetrier($failedTransport, self::createBus($emailTransport))->retryAvailable();
    }

    private static function createTransport(string $environmentKey, string $default): TransportInterface
    {
        $factory = new AmqpTransportFactory();

        return $factory->createTransport(
            self::environment($environmentKey, $default),
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
        $senders = new RuntimeServiceContainer(['emails' => $transport]);
        $retryStrategies = new RuntimeServiceContainer(['emails' => new MultiplierRetryStrategy()]);
        $failureSenders = new RuntimeServiceContainer(['emails' => $failureTransport]);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $retryStrategies));
        $eventDispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($failureSenders));

        return $eventDispatcher;
    }

    private static function environment(string $key, string $default): string
    {
        $value = getenv($key);

        return is_string($value) && strlen($value) > 0 ? $value : $default;
    }
}
