<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Repository\UserRepositoryInterface;
use DomainException;

final class UserAuthService
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;
    private const string INVALID_EMAIL_MESSAGE = 'Enter a valid email address.';
    private const string INVALID_PASSWORD_MESSAGE = 'Password must contain at least 8 characters.';
    private const string INVALID_CREDENTIALS_MESSAGE = 'Invalid email or password.';
    private const string DUPLICATE_EMAIL_MESSAGE = 'An account with this email already exists.';
    private const string UNVERIFIED_EMAIL_MESSAGE = 'Confirm your email address before logging in.';

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function register(string $email, string $password): User
    {
        $normalizedEmail = $email
            |> trim(...)
            |> strtolower(...);

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException(self::INVALID_EMAIL_MESSAGE);
        }

        if (strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new DomainException(self::INVALID_PASSWORD_MESSAGE);
        }

        if ($this->users->findByEmail($normalizedEmail) !== null) {
            throw new DomainException(self::DUPLICATE_EMAIL_MESSAGE);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        return $this->users->create($normalizedEmail, $passwordHash);
    }

    public function authenticate(string $email, string $password): User
    {
        $normalizedEmail = $email
            |> trim(...)
            |> strtolower(...);
        $user = $this->users->findByEmail($normalizedEmail);

        if ($user === null || !password_verify($password, $user->password)) {
            throw new DomainException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        if ($user->emailVerifiedAt === null) {
            throw new DomainException(self::UNVERIFIED_EMAIL_MESSAGE);
        }

        return $user;
    }

    public function findUnverifiedForResend(string $email, string $password): ?User
    {
        $normalizedEmail = $email
            |> trim(...)
            |> strtolower(...);
        $user = $this->users->findByEmail($normalizedEmail);

        if (
            $user === null
            || $user->emailVerifiedAt !== null
            || !password_verify($password, $user->password)
        ) {
            return null;
        }

        return $user;
    }
}
