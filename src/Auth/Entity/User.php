<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public string $email,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $emailVerifiedAt = null,
    ) {
    }

    public function isVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function hasPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }
}
