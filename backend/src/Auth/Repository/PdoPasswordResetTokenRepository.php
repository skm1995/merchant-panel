<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Shared\Database\SqlDateTime;
use DateTimeImmutable;
use PDO;

final readonly class PdoPasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    private const string CREATED_AT_QUERY = 'SELECT created_at FROM password_reset_tokens WHERE email = :email FOR UPDATE';
    private const string STORE_QUERY = 'INSERT INTO password_reset_tokens (email, token_hash, expires_at, created_at) VALUES (:email, :token_hash, :expires_at, :created_at) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), created_at = VALUES(created_at)';
    private const string FIND_VALID_QUERY = 'SELECT email FROM password_reset_tokens WHERE token_hash = :token_hash AND expires_at > :now FOR UPDATE';
    private const string DELETE_QUERY = 'DELETE FROM password_reset_tokens WHERE email = :email';
    private const string EMAIL_PARAMETER = 'email';
    private const string TOKEN_HASH_PARAMETER = 'token_hash';
    private const string EXPIRES_AT_PARAMETER = 'expires_at';
    private const string CREATED_AT_PARAMETER = 'created_at';
    private const string NOW_PARAMETER = 'now';

    public function __construct(private PDO $pdo)
    {
    }

    public function findCreatedAt(string $email): ?DateTimeImmutable
    {
        $statement = $this->pdo->prepare(self::CREATED_AT_QUERY);
        $statement->execute([self::EMAIL_PARAMETER => $email]);
        $createdAt = $statement->fetchColumn();

        return is_string($createdAt) ? new DateTimeImmutable($createdAt) : null;
    }

    public function store(string $email, string $tokenHash, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): void
    {
        $this->pdo->prepare(self::STORE_QUERY)->execute([
            self::EMAIL_PARAMETER => $email,
            self::TOKEN_HASH_PARAMETER => $tokenHash,
            self::EXPIRES_AT_PARAMETER => SqlDateTime::format($expiresAt),
            self::CREATED_AT_PARAMETER => SqlDateTime::format($createdAt),
        ]);
    }

    public function findEmailByValidToken(string $tokenHash, DateTimeImmutable $now): ?string
    {
        $statement = $this->pdo->prepare(self::FIND_VALID_QUERY);
        $statement->execute([
            self::TOKEN_HASH_PARAMETER => $tokenHash,
            self::NOW_PARAMETER => SqlDateTime::format($now),
        ]);
        $email = $statement->fetchColumn();

        return is_string($email) ? $email : null;
    }

    public function delete(string $email): void
    {
        $this->pdo->prepare(self::DELETE_QUERY)->execute([self::EMAIL_PARAMETER => $email]);
    }
}
