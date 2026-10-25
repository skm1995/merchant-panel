<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use App\Shared\Config\Environment;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
use App\Shared\Messaging\Exception\UnknownTransportException;
use Symfony\Component\Messenger\Worker;

/**
 * Builds Messenger buses and workers from config/messenger.php: named AMQP transports whose
 * DSNs come from the environment, and the transport each message class is sent to.
 */
final class MessengerRuntime
{
    public const string FAILED_TRANSPORT = 'failed';
    private const string CONFIGURATION_PATH = '%s/config/messenger.php';
    private const string TRANSPORTS_KEY = 'transports';
    private const string ROUTES_KEY = 'routes';

    /** @var array<string, TransportInterface> */
    private array $transports = [];

    /**
     * @param array<string, string> $transportDsnKeys environment keys of transport DSNs keyed by transport name
     * @param array<class-string, string> $routes transport names keyed by message class
     */
    public function __construct(
        private readonly array $transportDsnKeys,
        private readonly array $routes,
    ) {
    }

    public static function fromConfiguration(): self
    {
        /** @var array{transports: array<string, string>, routes: array<class-string, string>} $configuration */
        $configuration = require sprintf(self::CONFIGURATION_PATH, dirname(__DIR__, 3));

        return new self($configuration[self::TRANSPORTS_KEY], $configuration[self::ROUTES_KEY]);
    }

    /**
     * @param array<class-string, list<callable>> $handlers handlers for messages consumed in this runtime
     */
    public function bus(array $handlers = []): MessageBus
    {
        return new MessageBus([
            new SendMessageMiddleware(new RoutingSendersLocator($this->routes, $this->transportContainer())),
            new HandleMessageMiddleware(new HandlersLocator($handlers)),
        ]);
    }

    /**
     * Consumes every transport that a message class is routed to.
     *
     * @param array<class-string, list<callable>> $handlers
     */
    public function worker(array $handlers): Worker
    {
        $receivers = [];
        $retryStrategies = [];
        $failureSenders = [];

        foreach (array_unique(array_values($this->routes)) as $name) {
            $receivers[$name] = $this->transport($name);
            $retryStrategies[$name] = new MultiplierRetryStrategy();
            $failureSenders[$name] = $this->transport(self::FAILED_TRANSPORT);
        }

        $events = new EventDispatcher();
        $events->addSubscriber(new SendFailedMessageForRetryListener(
            new RuntimeServiceContainer($receivers),
            new RuntimeServiceContainer($retryStrategies),
        ));
        $events->addSubscriber(new SendFailedMessageToFailureTransportListener(new RuntimeServiceContainer($failureSenders)));

        return new Worker($receivers, $this->bus($handlers), $events);
    }

    public function failedMessageRetrier(): FailedMessageRetrier
    {
        return new FailedMessageRetrier($this->transport(self::FAILED_TRANSPORT), $this->bus());
    }

    public function transport(string $name): TransportInterface
    {
        return $this->transports[$name] ??= new AmqpTransportFactory()->createTransport(
            Environment::get($this->transportDsnKeys[$name] ?? throw new UnknownTransportException($name)),
            [],
            new PhpSerializer(),
        );
    }

    private function transportContainer(): RuntimeServiceContainer
    {
        $transports = [];

        foreach (array_unique($this->routes) as $name) {
            $transports[$name] = $this->transport($name);
        }

        return new RuntimeServiceContainer($transports);
    }
}
