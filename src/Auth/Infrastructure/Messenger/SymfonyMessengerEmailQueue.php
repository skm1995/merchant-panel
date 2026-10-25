<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Message\ConfirmationEmailQueueInterface;
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
