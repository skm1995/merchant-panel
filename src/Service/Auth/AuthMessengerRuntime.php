<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;

final class AuthMessengerRuntime
{
    private const string TRANSPORT_DSN_ENV = 'MESSENGER_TRANSPORT_DSN';
    private const string FAILURE_TRANSPORT_DSN_ENV = 'MESSENGER_FAILURE_TRANSPORT_DSN';
    private const string MAILER_DSN_ENV = 'MAILER_DSN';
    private const string APP_BASE_URL_ENV = 'APP_BASE_URL';
    private const string DEFAULT_TRANSPORT_DSN = 'amqp://app:app@rabbitmq:5672/%2f/emails';
    private const string DEFAULT_FAILURE_TRANSPORT_DSN = 'amqp://app:app@rabbitmq:5672/%2f/emails_failed';
    private const string DEFAULT_MAILER_DSN = 'smtp://mailhog:1025';
    private const string DEFAULT_APP_BASE_URL = 'http://localhost:8080';

    private function __construct(
        private readonly \Symfony\Component\Messenger\Transport\TransportInterface $transport,
        private readonly MessageBus $bus,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public static function createFromEnvironment(): self
    {
        $dsn = self::environment(self::TRANSPORT_DSN_ENV, self::DEFAULT_TRANSPORT_DSN);
        $transportFactory = new AmqpTransportFactory();
        $serializer = new PhpSerializer();
        $transport = $transportFactory->createTransport($dsn, [], $serializer);
        $failureDsn = self::environment(self::FAILURE_TRANSPORT_DSN_ENV, self::DEFAULT_FAILURE_TRANSPORT_DSN);
        $failureTransport = $transportFactory->createTransport($failureDsn, [], $serializer);
        $mailerDsn = self::environment(self::MAILER_DSN_ENV, self::DEFAULT_MAILER_DSN);
        $mailerTransport = $mailerDsn |> Transport::fromDsn(...);
        $mailer = new Mailer($mailerTransport);
        $baseUrl = self::environment(self::APP_BASE_URL_ENV, self::DEFAULT_APP_BASE_URL);
        $handler = new SendConfirmationEmailHandler($mailer, $baseUrl);
        $locator = new AuthMessageSendersLocator($transport);
        $senders = new RuntimeServiceContainer(['emails' => $transport]);
        $retryStrategies = new RuntimeServiceContainer(['emails' => new MultiplierRetryStrategy()]);
        $failureSenders = new RuntimeServiceContainer(['emails' => $failureTransport]);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $retryStrategies));
        $eventDispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($failureSenders));
        $bus = new MessageBus([
            new SendMessageMiddleware($locator),
            new HandleMessageMiddleware(new HandlersLocator([
                ConfirmationEmailMessage::class => [$handler],
            ])),
        ]);

        return new self($transport, $bus, $eventDispatcher);
    }

    public function queue(): ConfirmationEmailQueueInterface
    {
        return new SymfonyMessengerEmailQueue($this->bus);
    }

    public function createWorker(): Worker
    {
        return new Worker(['emails' => $this->transport], $this->bus, $this->eventDispatcher);
    }

    private static function environment(string $key, string $default): string
    {
        $value = getenv($key);

        return is_string($value) && strlen($value) > 0 ? $value : $default;
    }
}
