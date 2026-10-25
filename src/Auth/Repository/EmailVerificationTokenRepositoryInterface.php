<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use DateTimeImmutable;

interface EmailVerificationTokenRepositoryInterface
{
    public function store(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void;

    public function resend(
        string $email,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
        int $windowSeconds,
        int $maximumAttempts,
    ): bool;

    public function verify(string $tokenHash, DateTimeImmutable $verifiedAt): bool;
}
