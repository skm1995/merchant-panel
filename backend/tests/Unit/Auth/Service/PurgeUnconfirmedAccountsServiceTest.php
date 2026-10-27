<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\PurgeUnconfirmedAccountsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;

final class PurgeUnconfirmedAccountsServiceTest extends TestCase
{
    private const string NOW = '2026-10-27 03:00:00';
    private const string SEVEN_DAYS_AGO = '2026-10-20 03:00:00';
    private const int DELETED = 3;

    public function testDeletesAccountsLeftUnconfirmedForAWeek(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('deleteUnverifiedCreatedBefore')
            ->with(new DateTimeImmutable(self::SEVEN_DAYS_AGO))
            ->willReturn(self::DELETED);

        self::assertSame(self::DELETED, new PurgeUnconfirmedAccountsService($users, new FrozenClock(self::NOW))->execute());
    }
}
