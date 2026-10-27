<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Repository\UserRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use Psr\Clock\ClockInterface;

/**
 * Creates a confirmed account with merchant access in one step, for accounts an operator
 * sets up, such as the shared demo account; bin/create-merchant.php runs it. The password
 * follows the registration rules.
 */
final readonly class CreateMerchantAccountService
{
    public function __construct(
        private UserAuthService $authService,
        private UserRepositoryInterface $users,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $email, string $password): void
    {
        $this->transactions->run(function () use ($email, $password): void {
            $user = $this->authService->register($email, $password);
            $now = $this->clock->now();

            $this->users->markEmailConfirmed($user->email, $now);
            $this->users->grantMerchantAccess($user->email, $now);
        }, IsolationLevelEnum::READ_COMMITTED);
    }
}
