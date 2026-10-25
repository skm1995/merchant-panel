<?php

declare(strict_types=1);

namespace App\Service\Auth;

use DomainException;

final class RegisterUserService
{
    private const string EMAIL_INPUT_KEY = 'email';
    private const string PASSWORD_INPUT_KEY = 'password';
    private const string USER_EMAIL_SESSION_KEY = 'user_email';
    private const string HOME_ROUTE_PATH = '/';
    private const string MISSING_INPUT = "\0";

    public function __construct(private readonly UserAuthService $authService)
    {
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
            session_regenerate_id(true);
            $_SESSION[self::USER_EMAIL_SESSION_KEY] = $user->email;

            return AuthActionResult::redirect(self::HOME_ROUTE_PATH);
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
