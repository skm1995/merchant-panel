<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\EmailNotVerifiedException;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\Exception\InvalidEmailException;
use App\Auth\Exception\WeakPasswordException;
use App\Auth\Repository\UserRepositoryInterface;

final class UserAuthService
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;

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

        return $user?->hasPassword($password) === true ? $user : null;
    }

    private static function normalizeEmail(string $email): string
    {
        return $email
            |> trim(...)
            |> strtolower(...);
    }
}
