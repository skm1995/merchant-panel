<?php

declare(strict_types=1);

namespace App\Auth\Request;

use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

/**
 * A request for a password reset link, with the address to send it to.
 */
final readonly class ResetLinkRequest
{
    private const string EMAIL_FIELD = 'email';
    private const string MISSING_EMAIL_MESSAGE = 'The request must contain an email.';

    public function __construct(public string $email)
    {
    }

    public static function fromJson(Request $request): self
    {
        $email = $request->json()[self::EMAIL_FIELD] ?? null;

        if (!is_string($email)) {
            throw new InvalidRequestException(self::MISSING_EMAIL_MESSAGE);
        }

        return new self($email);
    }
}
