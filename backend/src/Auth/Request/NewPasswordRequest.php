<?php

declare(strict_types=1);

namespace App\Auth\Request;

use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

/**
 * A new password with the token from the reset link.
 */
final readonly class NewPasswordRequest
{
    private const string TOKEN_FIELD = 'token';
    private const string PASSWORD_FIELD = 'password';
    private const string MISSING_FIELDS_MESSAGE = 'The request must contain a token and a password.';

    public function __construct(public string $token, public string $password)
    {
    }

    public static function fromJson(Request $request): self
    {
        $data = $request->json();
        $token = $data[self::TOKEN_FIELD] ?? null;
        $password = $data[self::PASSWORD_FIELD] ?? null;

        if (!is_string($token) || $token === '' || !is_string($password)) {
            throw new InvalidRequestException(self::MISSING_FIELDS_MESSAGE);
        }

        return new self($token, $password);
    }
}
