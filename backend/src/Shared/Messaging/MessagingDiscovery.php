<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use App\Shared\DependencyInjection\ClassFinder;
use App\Shared\DependencyInjection\ServiceFactory;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Derives Messenger configuration from attributes: transports and routes from #[Message],
 * outbox types from OutboxMessageInterface, and handlers from #[AsMessageHandler].
 */
final class MessagingDiscovery
{
    private const string SOURCE_DIRECTORY = '%s/src';
    private const string ROOT_NAMESPACE = 'App';
    private const string DSN_VARIABLE_FORMAT = 'MESSENGER_%s_DSN';

    /** @param list<class-string> $classes */
    public function __construct(private readonly array $classes)
    {
    }

    #[ServiceFactory]
    public static function fromSource(): self
    {
        return new self(ClassFinder::find(sprintf(self::SOURCE_DIRECTORY, dirname(__DIR__, 3)), self::ROOT_NAMESPACE));
    }

    /** @return array<class-string, string> transport names keyed by message class */
    public function routes(): array
    {
        $routes = [];

        foreach ($this->classes as $class) {
            $message = (new ReflectionClass($class)->getAttributes(Message::class)[0] ?? null)?->newInstance();

            if ($message !== null) {
                $routes[$class] = $message->transport;
            }
        }

        return $routes;
    }

    /** @return array<string, string> DSN environment variables keyed by transport name */
    public function transportDsnKeys(): array
    {
        $keys = [];

        foreach ([...array_values($this->routes()), MessengerRuntime::FAILED_TRANSPORT] as $transport) {
            $keys[$transport] = sprintf(self::DSN_VARIABLE_FORMAT, strtoupper($transport));
        }

        ksort($keys);

        return $keys;
    }

    /** @return list<class-string<OutboxMessageInterface>> */
    public function outboxMessages(): array
    {
        return array_values(array_filter(
            $this->classes,
            static fn (string $class): bool => is_subclass_of($class, OutboxMessageInterface::class),
        ));
    }

    /**
     * Each handler service is built through the container when its first message arrives, so
     * a process builds only the handlers of the transports it consumes.
     *
     * @return array<class-string, list<callable>> handlers keyed by the message class they accept
     */
    public function handlers(ContainerInterface $services): array
    {
        /** @var array<class-string, list<callable>> $handlers */
        $handlers = [];

        foreach ($this->classes as $class) {
            foreach (new ReflectionClass($class)->getMethods() as $method) {
                $parameters = $method->getParameters();

                if ($method->getAttributes(AsMessageHandler::class) === [] || count($parameters) !== 1) {
                    continue;
                }

                $type = $parameters[0]->getType();

                if (!$type instanceof ReflectionNamedType) {
                    continue;
                }

                /** @var class-string $messageClass */
                $messageClass = $type->getName();
                $methodName = $method->getName();
                $handlers[$messageClass][] = static fn (object $message): mixed => $services->get($class)->{$methodName}($message);
            }
        }

        return $handlers;
    }
}
