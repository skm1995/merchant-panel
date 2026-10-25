<?php

declare(strict_types=1);

namespace App\Auth\Request;

use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class CredentialsRequest
{
    private const string EMAIL_FIELD = 'email';
    private const string PASSWORD_FIELD = 'password';
    private const string MISSING_FIELDS_MESSAGE = 'The form must contain an email and a password.';

    public function __construct(public string $email, public string $password)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $email = $request->post(self::EMAIL_FIELD);
        $password = $request->post(self::PASSWORD_FIELD);

        if ($email === null || $password === null) {
            throw new InvalidRequestException(self::MISSING_FIELDS_MESSAGE);
        }

        return new self($email, $password);
    }
}
