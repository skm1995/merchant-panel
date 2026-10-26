<?php

declare(strict_types=1);

namespace App\Shared\Http;

final class CsrfToken
{
    public const string FIELD_NAME = '_csrf_token';
    private const string SESSION_KEY = '_csrf_token';
    private const int RANDOM_BYTES = 32;

    public function __construct(private readonly Session $session)
    {
    }

    public function get(): ?string
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) ? $token : null;
    }

    /**
     * Generates a new token and stores it in the session, replacing any previous one.
     */
    public function create(): string
    {
        $token = random_bytes(self::RANDOM_BYTES) |> bin2hex(...);
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function isValid(?string $token): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
