<?php

declare(strict_types=1);

namespace App\Service\Auth;

interface ConfirmationEmailQueueInterface
{
    public function dispatch(ConfirmationEmailMessage $message): void;
}
