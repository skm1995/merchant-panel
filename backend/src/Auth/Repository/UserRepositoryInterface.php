<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use DateTimeImmutable;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function create(string $email, string $passwordHash): User;

    /**
     * Gives a confirmed account access to the merchant panel; an unconfirmed account and one
     * that already has access stay as they are.
     */
    public function grantMerchantAccess(string $email, DateTimeImmutable $grantedAt): void;
}
