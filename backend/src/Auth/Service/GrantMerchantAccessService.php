<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Exception\EmailNotVerifiedException;
use App\Auth\Exception\UnknownUserException;
use App\Auth\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Opens the merchant panel to a confirmed account. Registration alone never does, so only
 * people the shop trusts see orders and change prices; bin/grant-merchant.php runs this.
 */
final readonly class GrantMerchantAccessService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $email): void
    {
        $user = UserAuthService::normalizeEmail($email) |> $this->users->findByEmail(...)
            ?? throw new UnknownUserException();

        if (!$user->isVerified()) {
            throw new EmailNotVerifiedException();
        }

        $this->users->grantMerchantAccess($user->email, $this->clock->now());
    }
}
