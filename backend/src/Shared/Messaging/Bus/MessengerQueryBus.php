<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

/**
 * The query bus: answers each query at once. Queries change nothing, so they are not logged;
 * the request's access log already records how long it took.
 */
final readonly class MessengerQueryBus implements QueryBusInterface
{
    private MessageBus $bus;

    public function __construct(ContainerInterface $services)
    {
        $this->bus = new MessageBus([new HandleMessageMiddleware(new ConventionHandlersLocator($services))]);
    }

    public function ask(QueryInterface $query): mixed
    {
        return HandledResult::of($this->bus, $query);
    }
}
