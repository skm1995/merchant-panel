<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class ConfirmationEmailMessage
{
    public function __construct(public string $email, public string $token)
    {
    }
}
