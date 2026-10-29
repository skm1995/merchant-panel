<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use DateTimeImmutable;

final readonly class User
{
    private const string PASSWORD_STAMP_ALGORITHM = 'sha256';

    public function __construct(
        public string $email,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $emailVerifiedAt = null,
        public ?DateTimeImmutable $merchantSince = null,
    ) {
    }

    public function isVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function isMerchant(): bool
    {
        return $this->merchantSince !== null;
    }

    public function passwordMatches(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * A fingerprint of the current password hash, which changes whenever the password does.
     * A session keeps the stamp it signed in with, so changing the password ends it.
     */
    public function passwordStamp(): string
    {
        return hash(self::PASSWORD_STAMP_ALGORITHM, $this->passwordHash);
    }
}
