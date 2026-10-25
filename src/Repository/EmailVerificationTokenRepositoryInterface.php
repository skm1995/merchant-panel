<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;

interface EmailVerificationTokenRepositoryInterface
{
    public function store(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void;

    public function verify(string $tokenHash, DateTimeImmutable $verifiedAt): bool;
}
