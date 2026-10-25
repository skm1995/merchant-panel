<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use DateTimeImmutable;

interface EmailVerificationTokenRepositoryInterface
{
    public function store(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void;

    /**
     * Atomically replaces the token unless the resend limit for the current window is reached.
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
     * Consumes a valid token and marks its user's email as verified.
     */
    public function confirmEmail(string $tokenHash, DateTimeImmutable $confirmedAt): bool;
}
