<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Message\PasswordResetEmailMessage;
use App\Auth\Repository\PasswordResetTokenRepositoryInterface;
use App\Auth\Repository\UserRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\Messaging\Outbox\OutboxInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Emails a reset link to a confirmed account. Every address gets the same answer, so the
 * caller never learns which ones have an account; the token, its hash, and the email message
 * are stored in one transaction through the outbox.
 */
final readonly class RequestPasswordResetService
{
    public const int LINK_LIFETIME_SECONDS = 3600;
    public const int COOLDOWN_SECONDS = 300;
    private const string SECONDS_MODIFIER_FORMAT = '%+d seconds';

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordResetTokenRepositoryInterface $tokens,
        private OutboxInterface $outbox,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $email): void
    {
        $normalizedEmail = UserAuthService::normalizeEmail($email);
        UserAuthService::requireValidEmail($normalizedEmail);

        $this->transactions->run(function () use ($normalizedEmail): void {
            if ($this->users->findByEmail($normalizedEmail)?->isVerified() !== true) {
                return;
            }

            $now = $this->clock->now();
            $lastCreatedAt = $this->tokens->findCreatedAt($normalizedEmail);

            // A link sent minutes ago is still in the inbox; another one would only flood it.
            if ($lastCreatedAt !== null && $lastCreatedAt > self::shift($now, -self::COOLDOWN_SECONDS)) {
                return;
            }

            $token = ConfirmationToken::generate();
            $this->tokens->store($normalizedEmail, $token->hash(), $now, self::shift($now, self::LINK_LIFETIME_SECONDS));
            $this->outbox->add(new PasswordResetEmailMessage($normalizedEmail, $token->value), $now);
        }, IsolationLevelEnum::READ_COMMITTED);
    }

    private static function shift(DateTimeImmutable $moment, int $seconds): DateTimeImmutable
    {
        return $moment->modify(sprintf(self::SECONDS_MODIFIER_FORMAT, $seconds));
    }
}
