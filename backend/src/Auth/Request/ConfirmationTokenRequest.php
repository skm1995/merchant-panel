<?php

declare(strict_types=1);

namespace App\Auth\Request;

use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class ConfirmationTokenRequest
{
    private const string TOKEN_FIELD = 'token';
    private const string MISSING_TOKEN_MESSAGE = 'The confirmation token is missing.';

    public function __construct(public string $token)
    {
    }

    public static function fromJson(Request $request): self
    {
        $token = $request->json()[self::TOKEN_FIELD] ?? null;

        return self::fromValue(is_string($token) ? $token : null);
    }

    private static function fromValue(?string $token): self
    {
        if ($token === null || strlen($token) === 0) {
            throw new InvalidRequestException(self::MISSING_TOKEN_MESSAGE);
        }

        return new self($token);
    }
}
