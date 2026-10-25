<?php

declare(strict_types=1);

namespace App\Service\Auth;

use DomainException;

final class RegisterUserService
{
    private const string EMAIL_INPUT_KEY = 'email';
    private const string PASSWORD_INPUT_KEY = 'password';
    private const string CONFIRMATION_SENT_ROUTE_PATH = '/confirmation-sent';
    private const string MISSING_INPUT = "\0";
    private const string TOKEN_HASH_ALGORITHM = 'sha256';
    private const string TOKEN_EXPIRY = '+24 hours';

    public function __construct(
        private readonly UserAuthService $authService,
        private readonly \App\Repository\EmailVerificationTokenRepositoryInterface $tokens,
        private readonly ConfirmationEmailQueueInterface $emailQueue,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function execute(array $input): AuthActionResult
    {
        $email = self::stringInput($input, self::EMAIL_INPUT_KEY);

        try {
            $user = $this->authService->register(
                $email,
                self::stringInput($input, self::PASSWORD_INPUT_KEY),
            );
            $token = random_bytes(32) |> bin2hex(...);
            $this->tokens->store(
                $user->email,
                hash(self::TOKEN_HASH_ALGORITHM, $token),
                new \DateTimeImmutable(self::TOKEN_EXPIRY),
            );
            $this->emailQueue->dispatch(new ConfirmationEmailMessage($user->email, $token));

            return AuthActionResult::redirect(self::CONFIRMATION_SENT_ROUTE_PATH);
        } catch (DomainException $exception) {
            return AuthActionResult::failure($exception->getMessage(), $email);
        }
    }

    /** @param array<string, mixed> $input */
    private static function stringInput(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : self::MISSING_INPUT;
    }
}
