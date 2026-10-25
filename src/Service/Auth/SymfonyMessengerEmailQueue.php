<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\Messenger\MessageBusInterface;

final class SymfonyMessengerEmailQueue implements ConfirmationEmailQueueInterface
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function dispatch(ConfirmationEmailMessage $message): void
    {
        $this->bus->dispatch($message);
    }
}
