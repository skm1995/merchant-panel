<?php

declare(strict_types=1);

namespace App\Auth\Message;

interface ConfirmationEmailQueueInterface
{
    public function dispatch(ConfirmationEmailMessage $message): void;
}
