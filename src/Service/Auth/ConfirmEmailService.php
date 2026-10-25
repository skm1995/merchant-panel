<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Repository\EmailVerificationTokenRepositoryInterface;
use DateTimeImmutable;

final class ConfirmEmailService
{
    private const string TOKEN_HASH_ALGORITHM = 'sha256';
    private const string INVALID_TOKEN_MESSAGE = 'This confirmation link is invalid or has expired.';
    private const string CONFIRMED_ROUTE_PATH = '/email-confirmed';

    public function __construct(private readonly EmailVerificationTokenRepositoryInterface $tokens)
    {
    }

    public function execute(string $token): AuthActionResult
    {
        $verified = $this->tokens->verify(
            hash(self::TOKEN_HASH_ALGORITHM, $token),
            new DateTimeImmutable(),
        );

        return $verified
            ? AuthActionResult::redirect(self::CONFIRMED_ROUTE_PATH)
            : AuthActionResult::failure(self::INVALID_TOKEN_MESSAGE, null);
    }
}
