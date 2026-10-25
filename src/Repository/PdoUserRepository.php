<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use DateTimeImmutable;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    private const string FIND_QUERY = 'SELECT email, password, created_at FROM users WHERE email = :email';
    private const string INSERT_QUERY = 'INSERT INTO users (email, password, created_at) VALUES (:email, :password, CURRENT_TIMESTAMP)';
    private const string EMAIL_PARAMETER = 'email';
    private const string PASSWORD_PARAMETER = 'password';
    private const string CREATED_AT_COLUMN = 'created_at';

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

        return new User(
            (string) $row[self::EMAIL_PARAMETER],
            (string) $row[self::PASSWORD_PARAMETER],
            new DateTimeImmutable((string) $row[self::CREATED_AT_COLUMN]),
        );
    }

    public function create(string $email, string $passwordHash): User
    {
        $statement = $this->pdo->prepare(self::INSERT_QUERY);
        $statement->execute([
            self::EMAIL_PARAMETER => $email,
            self::PASSWORD_PARAMETER => $passwordHash,
        ]);

        return new User($email, $passwordHash, new DateTimeImmutable());
    }
}
