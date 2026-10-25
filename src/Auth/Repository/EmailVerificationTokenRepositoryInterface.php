<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use DateTimeImmutable;

interface EmailVerificationTokenRepositoryInterface
{
    public function store(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void;

    /**
     * Replaces the token unless the resend limit for the current window is reached. Callers
     * must run it inside a transaction, because it locks the resend counter row.
     */
    public function storeWithinResendLimit(
        string $email,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
        int $windowSeconds,
        int $maximumAttempts,
    ): bool;

    /**
     * Consumes a valid token and marks its user's email as verified. Callers must run it
     * inside a transaction.
     */
    public function confirmEmail(string $tokenHash, DateTimeImmutable $confirmedAt): bool;
}
