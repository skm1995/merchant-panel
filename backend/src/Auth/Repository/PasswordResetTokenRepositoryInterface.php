<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use DateTimeImmutable;

/**
 * Password reset links, one per account, stored as token hashes. Callers run every method
 * inside a transaction, because the reads lock the rows they find.
 */
interface PasswordResetTokenRepositoryInterface
{
    /**
     * When the account's current link was created, or null when it has none.
     */
    public function findCreatedAt(string $email): ?DateTimeImmutable;

    /**
     * Stores the account's link, replacing an earlier one.
     */
    public function store(string $email, string $tokenHash, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): void;

    /**
     * The account whose unexpired link has this token hash, or null.
     */
    public function findEmailByValidToken(string $tokenHash, DateTimeImmutable $now): ?string;

    public function delete(string $email): void;
}
