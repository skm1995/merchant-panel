<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\ResendLimitReachedException;
use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use DateTimeImmutable;

final class RegisterUserService
{
    public const int MAXIMUM_RESEND_ATTEMPTS = 5;
    public const int RESEND_WINDOW_SECONDS = 86400;
    private const string TOKEN_EXPIRY = '+24 hours';

    public function __construct(
        private readonly UserAuthService $authService,
        private readonly EmailVerificationTokenRepositoryInterface $tokens,
        private readonly ConfirmationEmailQueueInterface $emailQueue,
    ) {
    }

    public function execute(string $email, string $password): void
    {
        try {
            $user = $this->authService->register($email, $password);
        } catch (DuplicateEmailException $exception) {
            $this->resendConfirmation(
                $this->authService->findUnverifiedForResend($email, $password) ?? throw $exception,
            );

            return;
        }

        $this->sendInitialConfirmation($user);
    }

    private function sendInitialConfirmation(User $user): void
    {
        $token = ConfirmationToken::generate();
        $this->tokens->store($user->email, $token->hash(), new DateTimeImmutable(self::TOKEN_EXPIRY));
        $this->dispatch($user, $token);
    }

    private function resendConfirmation(User $user): void
    {
        $token = ConfirmationToken::generate();
        $stored = $this->tokens->storeWithinResendLimit(
            $user->email,
            $token->hash(),
            new DateTimeImmutable(self::TOKEN_EXPIRY),
            new DateTimeImmutable(),
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
        $this->emailQueue->dispatch(new ConfirmationEmailMessage($user->email, $token->value));
    }
}
