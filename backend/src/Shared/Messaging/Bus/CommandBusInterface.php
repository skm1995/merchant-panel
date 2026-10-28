<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

interface CommandBusInterface
{
    /**
     * Handles the command at once and returns its handler's result; the handler's exceptions
     * reach the caller unchanged.
     *
     * @template TResult
     * @param CommandInterface<TResult> $command
     * @return TResult
     */
    public function dispatch(CommandInterface $command): mixed;
}
