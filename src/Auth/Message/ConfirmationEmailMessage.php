<?php

declare(strict_types=1);

namespace App\Auth\Message;

final readonly class ConfirmationEmailMessage
{
    public function __construct(public string $email, public string $token)
    {
    }
}
