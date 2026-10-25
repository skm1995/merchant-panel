<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class AuthActionResult
{
    private function __construct(
        public ?string $redirectPath,
        public ?string $errorMessage,
        public ?string $email,
    ) {
    }

    public static function redirect(string $path): self
    {
        return new self($path, null, null);
    }

    public static function failure(string $message, ?string $email): self
    {
        return new self(null, $message, $email);
    }
}
