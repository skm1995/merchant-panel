<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Entity;

use App\Auth\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\CheapPasswordHash;

final class UserTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string WRONG_PASSWORD = 'incorrect-password';

    public function testIsVerifiedOnlyAfterEmailConfirmation(): void
    {
        $unverified = new User(self::EMAIL, self::PASSWORD, new DateTimeImmutable());
        $verified = new User(self::EMAIL, self::PASSWORD, new DateTimeImmutable(), new DateTimeImmutable());

        self::assertFalse($unverified->isVerified());
        self::assertTrue($verified->isVerified());
    }

    public function testMatchesOnlyThePasswordUsedForTheHash(): void
    {
        $user = new User(self::EMAIL, CheapPasswordHash::of(self::PASSWORD), new DateTimeImmutable());

        self::assertTrue($user->passwordMatches(self::PASSWORD));
        self::assertFalse($user->passwordMatches(self::WRONG_PASSWORD));
    }

    public function testIsAMerchantOnlyOnceAccessWasGranted(): void
    {
        $now = new DateTimeImmutable();

        self::assertFalse(new User(self::EMAIL, self::PASSWORD, $now, $now)->isMerchant());
        self::assertTrue(new User(self::EMAIL, self::PASSWORD, $now, $now, $now)->isMerchant());
    }
}
