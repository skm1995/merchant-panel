<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;
use PDO;
use Throwable;

final class PdoEmailVerificationTokenRepository implements EmailVerificationTokenRepositoryInterface
{
    private const string STORE_QUERY = 'INSERT INTO email_verification_tokens (email, token_hash, expires_at) VALUES (:email, :token_hash, :expires_at) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)';
    private const string FIND_VALID_TOKEN_QUERY = 'SELECT email FROM email_verification_tokens WHERE token_hash = :token_hash AND expires_at > :verified_at FOR UPDATE';
    private const string VERIFY_USER_QUERY = 'UPDATE users SET email_verified_at = :verified_at WHERE email = :email AND email_verified_at IS NULL';
    private const string DELETE_TOKEN_QUERY = 'DELETE FROM email_verification_tokens WHERE token_hash = :token_hash';
    private const string EMAIL_PARAMETER = 'email';
    private const string TOKEN_HASH_PARAMETER = 'token_hash';
    private const string EXPIRES_AT_PARAMETER = 'expires_at';
    private const string VERIFIED_AT_PARAMETER = 'verified_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function store(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(self::STORE_QUERY);
        $statement->execute([
            self::EMAIL_PARAMETER => $email,
            self::TOKEN_HASH_PARAMETER => $tokenHash,
            self::EXPIRES_AT_PARAMETER => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function verify(string $tokenHash, DateTimeImmutable $verifiedAt): bool
    {
        $this->pdo->beginTransaction();

        try {
            $findStatement = $this->pdo->prepare(self::FIND_VALID_TOKEN_QUERY);
            $findStatement->execute([
                self::TOKEN_HASH_PARAMETER => $tokenHash,
                self::VERIFIED_AT_PARAMETER => $verifiedAt->format('Y-m-d H:i:s'),
            ]);
            $email = $findStatement->fetchColumn();

            if (!is_string($email)) {
                $this->pdo->commit();

                return false;
            }

            $verifyStatement = $this->pdo->prepare(self::VERIFY_USER_QUERY);
            $verifyStatement->execute([
                self::VERIFIED_AT_PARAMETER => $verifiedAt->format('Y-m-d H:i:s'),
                self::EMAIL_PARAMETER => $email,
            ]);
            $verified = $verifyStatement->rowCount() === 1;

            $deleteStatement = $this->pdo->prepare(self::DELETE_TOKEN_QUERY);
            $deleteStatement->execute([self::TOKEN_HASH_PARAMETER => $tokenHash]);
            $this->pdo->commit();

            return $verified;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
