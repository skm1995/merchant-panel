<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\EmailNotVerifiedException;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\Exception\InvalidEmailException;
use App\Auth\Exception\UnsupportedPasswordException;
use App\Auth\Exception\WeakPasswordException;
use App\Auth\Repository\UserRepositoryInterface;

final class UserAuthService
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;
    private const int MAXIMUM_PASSWORD_BYTES = 72;
    private const string UNKNOWN_ACCOUNT_PASSWORD_HASH = '$2y$12$TLCFeM4OGbtb46XOa2M5nO0IDcB17iF2etw2mYX5/pAQ75EmDLRb6';
    private const string NUL_CHARACTER = "\0";

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function register(string $email, string $password): User
    {
        $normalizedEmail = self::normalizeEmail($email);

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailException();
        }

        if (strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new WeakPasswordException(self::MINIMUM_PASSWORD_LENGTH);
        }

        if (strlen($password) > self::MAXIMUM_PASSWORD_BYTES || str_contains($password, self::NUL_CHARACTER)) {
            throw new UnsupportedPasswordException(self::MAXIMUM_PASSWORD_BYTES);
        }

        if ($this->users->findByEmail($normalizedEmail) !== null) {
            throw new DuplicateEmailException();
        }

        return $this->users->create($normalizedEmail, password_hash($password, PASSWORD_DEFAULT));
    }

    public function authenticate(string $email, string $password): User
    {
        $user = $this->findWithPassword($email, $password)
            ?? throw new InvalidCredentialsException();

        if (!$user->isVerified()) {
            throw new EmailNotVerifiedException();
        }

        return $user;
    }

    public function findUnverifiedForResend(string $email, string $password): ?User
    {
        $user = $this->findWithPassword($email, $password);

        return $user?->isVerified() === false ? $user : null;
    }

    private function findWithPassword(string $email, string $password): ?User
    {
        $user = self::normalizeEmail($email) |> $this->users->findByEmail(...);

        if ($user === null) {
            // Checking against a hash of an unknown password makes an unknown address take as
            // long as a wrong password, so response times do not reveal which accounts exist.
            password_verify($password, self::UNKNOWN_ACCOUNT_PASSWORD_HASH);

            return null;
        }

        return $user->passwordMatches($password) ? $user : null;
    }

    public static function normalizeEmail(string $email): string
    {
        return $email
            |> trim(...)
            |> strtolower(...);
    }
}
