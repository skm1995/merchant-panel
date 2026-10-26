<?php

declare(strict_types=1);

namespace App\Auth\Request;

use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class CredentialsRequest
{
    private const string EMAIL_FIELD = 'email';
    private const string PASSWORD_FIELD = 'password';
    private const string MISSING_FIELDS_MESSAGE = 'The request must contain an email and a password.';

    public function __construct(public string $email, public string $password)
    {
    }

    public static function fromForm(Request $request): self
    {
        return self::fromValues($request->post(self::EMAIL_FIELD), $request->post(self::PASSWORD_FIELD));
    }

    public static function fromJson(Request $request): self
    {
        $data = $request->json();

        return self::fromValues($data[self::EMAIL_FIELD] ?? null, $data[self::PASSWORD_FIELD] ?? null);
    }

    private static function fromValues(mixed $email, mixed $password): self
    {
        if (!is_string($email) || !is_string($password)) {
            throw new InvalidRequestException(self::MISSING_FIELDS_MESSAGE);
        }

        return new self($email, $password);
    }
}
