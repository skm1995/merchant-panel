<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\EmailNotVerifiedException;
use App\Auth\Exception\UnknownUserException;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\GrantMerchantAccessService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;

final class GrantMerchantAccessServiceTest extends TestCase
{
    private const string EMAIL = 'merchant@example.com';
    private const string PASSWORD_HASH = 'password-hash';
    private const string NOW = '2026-10-27 12:00:00';

    public function testGrantsAccessToAConfirmedUserFoundByTheNormalizedEmail(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->with(self::EMAIL)->willReturn(new User(self::EMAIL, self::PASSWORD_HASH, new DateTimeImmutable(), new DateTimeImmutable()));
        $users->expects(self::once())->method('grantMerchantAccess')->with(self::EMAIL, new DateTimeImmutable(self::NOW));

        new GrantMerchantAccessService($users, new FrozenClock(self::NOW))->execute(' Merchant@Example.com ');
    }

    public function testRefusesAnUnknownEmail(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects(self::never())->method('grantMerchantAccess');

        $this->expectException(UnknownUserException::class);
        new GrantMerchantAccessService($users, new FrozenClock(self::NOW))->execute(self::EMAIL);
    }

    public function testRefusesAnUnconfirmedEmail(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(new User(self::EMAIL, self::PASSWORD_HASH, new DateTimeImmutable()));
        $users->expects(self::never())->method('grantMerchantAccess');

        $this->expectException(EmailNotVerifiedException::class);
        new GrantMerchantAccessService($users, new FrozenClock(self::NOW))->execute(self::EMAIL);
    }
}
