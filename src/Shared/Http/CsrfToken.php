<?php

declare(strict_types=1);

namespace App\Shared\Http;

final class CsrfToken
{
    public const string SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function get(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(mixed $token): bool
    {
        return is_string($token) && hash_equals(self::get(), $token);
    }
}
