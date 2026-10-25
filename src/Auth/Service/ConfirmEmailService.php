<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Exception\InvalidConfirmationTokenException;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use DateTimeImmutable;

final class ConfirmEmailService
{
    public function __construct(private readonly EmailVerificationTokenRepositoryInterface $tokens)
    {
    }

    public function execute(string $token): void
    {
        $confirmed = $this->tokens->confirmEmail(
            ConfirmationToken::fromString($token)->hash(),
            new DateTimeImmutable(),
        );

        if (!$confirmed) {
            throw new InvalidConfirmationTokenException();
        }
    }
}
