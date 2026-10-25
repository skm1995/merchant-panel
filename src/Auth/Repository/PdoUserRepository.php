<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoUserRepository implements UserRepositoryInterface
{
    private const string FIND_QUERY = 'SELECT email, password, created_at, email_verified_at FROM users WHERE email = :email';
    private const string INSERT_QUERY = 'INSERT INTO users (email, password, created_at) VALUES (:email, :password, CURRENT_TIMESTAMP)';
    private const string EMAIL_PARAMETER = 'email';
    private const string PASSWORD_PARAMETER = 'password';
    private const string EMAIL_COLUMN = 'email';
    private const string PASSWORD_COLUMN = 'password';
    private const string CREATED_AT_COLUMN = 'created_at';
    private const string EMAIL_VERIFIED_AT_COLUMN = 'email_verified_at';
    private const int DRIVER_ERROR_CODE_INDEX = 1;
    private const int DUPLICATE_KEY_ERROR_CODE = 1062;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare(self::FIND_QUERY);
        $statement->execute([self::EMAIL_PARAMETER => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $emailVerifiedAt = $row[self::EMAIL_VERIFIED_AT_COLUMN] ?? null;

        return new User(
            (string) $row[self::EMAIL_COLUMN],
            (string) $row[self::PASSWORD_COLUMN],
            new DateTimeImmutable((string) $row[self::CREATED_AT_COLUMN]),
            is_string($emailVerifiedAt) ? new DateTimeImmutable($emailVerifiedAt) : null,
        );
    }

    public function create(string $email, string $passwordHash): User
    {
        try {
            $this->pdo->prepare(self::INSERT_QUERY)->execute([
                self::EMAIL_PARAMETER => $email,
                self::PASSWORD_PARAMETER => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[self::DRIVER_ERROR_CODE_INDEX] ?? null) === self::DUPLICATE_KEY_ERROR_CODE) {
                throw new DuplicateEmailException($exception);
            }

            throw $exception;
        }

        return new User($email, $passwordHash, new DateTimeImmutable());
    }
}
