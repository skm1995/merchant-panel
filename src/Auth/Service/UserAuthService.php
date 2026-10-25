<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Repository\UserRepositoryInterface;
use DomainException;

final class UserAuthService
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;
    private const string INVALID_EMAIL_MESSAGE = 'Enter a valid email address.';
    private const string INVALID_PASSWORD_MESSAGE = 'Password must contain at least 8 characters.';
    private const string INVALID_CREDENTIALS_MESSAGE = 'Invalid email or password.';
    private const string UNVERIFIED_EMAIL_MESSAGE = 'Confirm your email address before logging in.';

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function register(string $email, string $password): User
    {
        $normalizedEmail = self::normalizeEmail($email);

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException(self::INVALID_EMAIL_MESSAGE);
        }

        if (strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new DomainException(self::INVALID_PASSWORD_MESSAGE);
        }

        if ($this->users->findByEmail($normalizedEmail) !== null) {
            throw new DuplicateEmailException();
        }

        return $this->users->create($normalizedEmail, password_hash($password, PASSWORD_DEFAULT));
    }

    public function authenticate(string $email, string $password): User
    {
        $user = $this->findWithPassword($email, $password)
            ?? throw new DomainException(self::INVALID_CREDENTIALS_MESSAGE);

        if (!$user->isVerified()) {
            throw new DomainException(self::UNVERIFIED_EMAIL_MESSAGE);
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

        return $user?->hasPassword($password) === true ? $user : null;
    }

    private static function normalizeEmail(string $email): string
    {
        return $email
            |> trim(...)
            |> strtolower(...);
    }
}
