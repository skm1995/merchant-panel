<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Exception\InvalidResetTokenException;
use App\Auth\Repository\PasswordResetTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use Psr\Clock\ClockInterface;

/**
 * Sets a new password with a reset link's token and uses the link up. Every session of the
 * account ends with it, because a session keeps the password stamp it signed in with.
 */
final readonly class ResetPasswordService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordResetTokenRepositoryInterface $tokens,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $token, string $password): void
    {
        UserAuthService::requireValidPassword($password);
        $tokenHash = ConfirmationToken::fromString($token)->hash();

        $this->transactions->run(function () use ($tokenHash, $password): void {
            $email = $this->tokens->findEmailByValidToken($tokenHash, $this->clock->now())
                ?? throw new InvalidResetTokenException();

            $this->users->changePassword($email, UserAuthService::hashPassword($password));
            $this->tokens->delete($email);
        }, IsolationLevelEnum::READ_COMMITTED);
    }
}
