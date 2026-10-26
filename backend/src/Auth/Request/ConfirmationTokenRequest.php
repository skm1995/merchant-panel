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

    public static function fromLink(Request $request): self
    {
        return self::fromValue($request->query(self::TOKEN_FIELD));
    }

    public static function fromForm(Request $request): self
    {
        return self::fromValue($request->post(self::TOKEN_FIELD));
    }

    private static function fromValue(?string $token): self
    {
        return new self($token ?? throw new InvalidRequestException(self::MISSING_TOKEN_MESSAGE));
    }
}
