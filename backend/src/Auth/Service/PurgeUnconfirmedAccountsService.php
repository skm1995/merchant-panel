<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Deletes accounts whose owners never confirmed their email address within a week, so
 * abandoned or mistyped registrations do not keep personal data or block the address.
 * bin/purge-unconfirmed-accounts.php runs it daily on the server.
 */
final readonly class PurgeUnconfirmedAccountsService
{
    private const string RETENTION_MODIFIER = '-7 days';

    public function __construct(
        private UserRepositoryInterface $users,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): int
    {
        return $this->users->deleteUnverifiedCreatedBefore($this->clock->now()->modify(self::RETENTION_MODIFIER));
    }
}
