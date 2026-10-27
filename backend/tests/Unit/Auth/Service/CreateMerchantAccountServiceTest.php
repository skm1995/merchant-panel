<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\WeakPasswordException;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\CreateMerchantAccountService;
use App\Auth\Service\UserAuthService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class CreateMerchantAccountServiceTest extends TestCase
{
    private const string EMAIL = 'demo@example.com';
    private const string PASSWORD = 'demo-merchant-password';
    private const string SHORT_PASSWORD = 'short';
    private const string NOW = '2026-10-27 12:00:00';

    public function testCreatesAConfirmedAccountWithMerchantAccess(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects(self::once())->method('create')
            ->with(self::EMAIL, self::callback(static fn (string $hash): bool => password_verify(self::PASSWORD, $hash)))
            ->willReturn(new User(self::EMAIL, 'hash', $now));
        $users->expects(self::once())->method('markEmailConfirmed')->with(self::EMAIL, $now);
        $users->expects(self::once())->method('grantMerchantAccess')->with(self::EMAIL, $now);

        $this->service($users)->execute(' Demo@Example.com ', self::PASSWORD);
    }

    public function testRefusesAnEmailThatAlreadyHasAnAccount(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(new User(self::EMAIL, 'hash', new DateTimeImmutable()));
        $users->expects(self::never())->method('grantMerchantAccess');

        $this->expectException(DuplicateEmailException::class);
        $this->service($users)->execute(self::EMAIL, self::PASSWORD);
    }

    public function testAppliesTheRegistrationPasswordRules(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects(self::never())->method('create');

        $this->expectException(WeakPasswordException::class);
        $this->service($users)->execute(self::EMAIL, self::SHORT_PASSWORD);
    }

    private function service(UserRepositoryInterface $users): CreateMerchantAccountService
    {
        return new CreateMerchantAccountService(new UserAuthService($users), $users, new ImmediateTransactionRunner(), new FrozenClock(self::NOW));
    }
}
