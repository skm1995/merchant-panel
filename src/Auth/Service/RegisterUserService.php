<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\ResendLimitReachedException;
use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\Messaging\Outbox\OutboxInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Registers a user, or resends the confirmation to an unverified user who repeats the
 * registration. The user, the token, and the email message are stored in one transaction
 * through the outbox, so a broker outage never leaves an account without its email.
 */
final class RegisterUserService
{
    public const int MAXIMUM_RESEND_ATTEMPTS = 5;
    public const int RESEND_WINDOW_SECONDS = 86400;
    private const string TOKEN_EXPIRY = '+24 hours';

    public function __construct(
        private readonly UserAuthService $authService,
        private readonly EmailVerificationTokenRepositoryInterface $tokens,
        private readonly OutboxInterface $outbox,
        private readonly TransactionRunnerInterface $transactions,
        private readonly ClockInterface $clock,
    ) {
    }

    public function execute(string $email, string $password): void
    {
        $this->transactions->run(function () use ($email, $password): void {
            try {
                $user = $this->authService->register($email, $password);
            } catch (DuplicateEmailException $exception) {
                $this->resendConfirmation(
                    $this->authService->findUnverifiedForResend($email, $password) ?? throw $exception,
                );

                return;
            }

            $this->sendInitialConfirmation($user);
        }, IsolationLevelEnum::READ_COMMITTED);
    }

    private function sendInitialConfirmation(User $user): void
    {
        $token = ConfirmationToken::generate();
        $this->tokens->store($user->email, $token->hash(), $this->tokenExpiry());
        $this->dispatch($user, $token);
    }

    private function resendConfirmation(User $user): void
    {
        $token = ConfirmationToken::generate();
        $stored = $this->tokens->storeWithinResendLimit(
            $user->email,
            $token->hash(),
            $this->tokenExpiry(),
            $this->clock->now(),
            self::RESEND_WINDOW_SECONDS,
            self::MAXIMUM_RESEND_ATTEMPTS,
        );

        if (!$stored) {
            throw new ResendLimitReachedException();
        }

        $this->dispatch($user, $token);
    }

    private function dispatch(User $user, ConfirmationToken $token): void
    {
        $this->outbox->add(new ConfirmationEmailMessage($user->email, $token->value), $this->clock->now());
    }

    private function tokenExpiry(): DateTimeImmutable
    {
        return $this->clock->now()->modify(self::TOKEN_EXPIRY);
    }
}
