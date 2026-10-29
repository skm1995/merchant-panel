<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Auth\Entity\User;
use App\Auth\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use LogicException;

/**
 * User repository for routing and middleware tests that are not about access control: everyone
 * who signs in is a verified merchant. MerchantMiddlewareTest covers who is refused.
 */
final class MerchantUserRepository implements UserRepositoryInterface
{
    private const string PASSWORD_HASH = 'merchant-password-hash';
    private const string READ_ONLY_MESSAGE = 'These tests only look merchants up.';

    public function findByEmail(string $email): User
    {
        $now = new DateTimeImmutable();

        return new User($email, self::PASSWORD_HASH, $now, $now, $now);
    }

    public function create(string $email, string $passwordHash): User
    {
        throw new LogicException(self::READ_ONLY_MESSAGE);
    }

    public function changePassword(string $email, string $passwordHash): void
    {
        throw new LogicException(self::READ_ONLY_MESSAGE);
    }

    public function markEmailConfirmed(string $email, DateTimeImmutable $confirmedAt): void
    {
        throw new LogicException(self::READ_ONLY_MESSAGE);
    }

    public function grantMerchantAccess(string $email, DateTimeImmutable $grantedAt): void
    {
        throw new LogicException(self::READ_ONLY_MESSAGE);
    }

    public function deleteUnverifiedCreatedBefore(DateTimeImmutable $cutoff): int
    {
        throw new LogicException(self::READ_ONLY_MESSAGE);
    }
}
