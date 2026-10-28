<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

/**
 * The command bus: handles each command at once, in the caller's process, and logs it.
 * Commands that run later, such as an order's expiry, are recorded in the outbox instead and
 * reach their handler through the worker.
 */
final readonly class MessengerCommandBus implements CommandBusInterface
{
    private MessageBus $bus;

    public function __construct(ContainerInterface $services, LoggerInterface $logger)
    {
        $this->bus = new MessageBus([
            new CommandLogMiddleware($logger),
            new HandleMessageMiddleware(new ConventionHandlersLocator($services)),
        ]);
    }

    public function dispatch(CommandInterface $command): mixed
    {
        return HandledResult::of($this->bus, $command);
    }
}
