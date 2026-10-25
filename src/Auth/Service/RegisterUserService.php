<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Auth\Message\ConfirmationEmailQueueInterface;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use DateTimeImmutable;
use DomainException;

final class RegisterUserService
{
    private const string EMAIL_INPUT_KEY = 'email';
    private const string PASSWORD_INPUT_KEY = 'password';
    private const string CONFIRMATION_SENT_ROUTE_PATH = '/confirmation-sent';
    private const string MISSING_INPUT = "\0";
    private const string TOKEN_HASH_ALGORITHM = 'sha256';
    private const string TOKEN_EXPIRY = '+24 hours';
    private const int MAXIMUM_RESEND_ATTEMPTS = 5;
    private const int RESEND_WINDOW_SECONDS = 86400;
    private const string RESEND_LIMIT_MESSAGE = 'You have reached the email confirmation resend limit. Try again later.';

    public function __construct(
        private readonly UserAuthService $authService,
        private readonly EmailVerificationTokenRepositoryInterface $tokens,
        private readonly ConfirmationEmailQueueInterface $emailQueue,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function execute(array $input): AuthActionResult
    {
        $email = self::stringInput($input, self::EMAIL_INPUT_KEY);
        $password = self::stringInput($input, self::PASSWORD_INPUT_KEY);

        try {
            $user = $this->authService->register($email, $password);
        } catch (DomainException $exception) {
            $user = $this->authService->findUnverifiedForResend($email, $password);

            if ($user !== null) {
                if (!$this->sendConfirmation($user->email, true)) {
                    return AuthActionResult::failure(self::RESEND_LIMIT_MESSAGE, $email);
                }

                return AuthActionResult::redirect(self::CONFIRMATION_SENT_ROUTE_PATH);
            }

            return AuthActionResult::failure($exception->getMessage(), $email);
        }

        $this->sendConfirmation($user->email, false);

        return AuthActionResult::redirect(self::CONFIRMATION_SENT_ROUTE_PATH);
    }

    /** @param array<string, mixed> $input */
    private static function stringInput(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : self::MISSING_INPUT;
    }

    private function sendConfirmation(string $email, bool $isResend): bool
    {
        $token = random_bytes(32) |> bin2hex(...);
        $tokenHash = hash(self::TOKEN_HASH_ALGORITHM, $token);
        $expiresAt = new DateTimeImmutable(self::TOKEN_EXPIRY);

        if ($isResend) {
            $allowed = $this->tokens->resend(
                $email,
                $tokenHash,
                $expiresAt,
                new DateTimeImmutable(),
                self::RESEND_WINDOW_SECONDS,
                self::MAXIMUM_RESEND_ATTEMPTS,
            );

            if (!$allowed) {
                return false;
            }
        } else {
            $this->tokens->store($email, $tokenHash, $expiresAt);
        }

        $this->emailQueue->dispatch(new ConfirmationEmailMessage($email, $token));

        return true;
    }
}
