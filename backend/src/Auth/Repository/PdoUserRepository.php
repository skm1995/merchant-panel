<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Shared\Database\MysqlError;
use App\Shared\Database\RecordFetcher;
use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;

final class PdoUserRepository implements UserRepositoryInterface
{
    private const string FIND_QUERY = 'SELECT email, password AS passwordHash, created_at AS createdAt, email_verified_at AS emailVerifiedAt, merchant_since AS merchantSince FROM users WHERE email = :email';
    private const string INSERT_QUERY = 'INSERT INTO users (email, password, created_at) VALUES (:email, :password, CURRENT_TIMESTAMP)';
    private const string CHANGE_PASSWORD_QUERY = 'UPDATE users SET password = :password WHERE email = :email';
    private const string GRANT_MERCHANT_QUERY = 'UPDATE users SET merchant_since = :granted_at WHERE email = :email AND email_verified_at IS NOT NULL AND merchant_since IS NULL';
    private const string GRANTED_AT_PARAMETER = 'granted_at';
    private const string CONFIRM_EMAIL_QUERY = 'UPDATE users SET email_verified_at = :confirmed_at WHERE email = :email AND email_verified_at IS NULL';
    private const string CONFIRMED_AT_PARAMETER = 'confirmed_at';
    private const string DELETE_UNVERIFIED_QUERY = 'DELETE FROM users WHERE email_verified_at IS NULL AND created_at < :cutoff';
    private const string CUTOFF_PARAMETER = 'cutoff';
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private const string EMAIL_PARAMETER = 'email';
    private const string PASSWORD_PARAMETER = 'password';
    private const string NOT_STORED_MESSAGE = 'The created user could not be read back.';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare(self::FIND_QUERY);
        $statement->execute([self::EMAIL_PARAMETER => $email]);

        return RecordFetcher::one($statement, UserRecord::class)?->toEntity();
    }

    public function create(string $email, string $passwordHash): User
    {
        try {
            $this->pdo->prepare(self::INSERT_QUERY)->execute([
                self::EMAIL_PARAMETER => $email,
                self::PASSWORD_PARAMETER => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            if (MysqlError::isDuplicateKey($exception)) {
                throw new DuplicateEmailException($exception);
            }

            throw $exception;
        }

        return $this->findByEmail($email) ?? throw new LogicException(self::NOT_STORED_MESSAGE);
    }

    public function changePassword(string $email, string $passwordHash): void
    {
        $this->pdo->prepare(self::CHANGE_PASSWORD_QUERY)->execute([
            self::EMAIL_PARAMETER => $email,
            self::PASSWORD_PARAMETER => $passwordHash,
        ]);
    }

    public function markEmailConfirmed(string $email, DateTimeImmutable $confirmedAt): void
    {
        $this->pdo->prepare(self::CONFIRM_EMAIL_QUERY)->execute([
            self::CONFIRMED_AT_PARAMETER => $confirmedAt->format(self::DATE_TIME_FORMAT),
            self::EMAIL_PARAMETER => $email,
        ]);
    }

    public function grantMerchantAccess(string $email, DateTimeImmutable $grantedAt): void
    {
        $this->pdo->prepare(self::GRANT_MERCHANT_QUERY)->execute([
            self::GRANTED_AT_PARAMETER => $grantedAt->format(self::DATE_TIME_FORMAT),
            self::EMAIL_PARAMETER => $email,
        ]);
    }

    public function deleteUnverifiedCreatedBefore(DateTimeImmutable $cutoff): int
    {
        $statement = $this->pdo->prepare(self::DELETE_UNVERIFIED_QUERY);
        $statement->execute([self::CUTOFF_PARAMETER => $cutoff->format(self::DATE_TIME_FORMAT)]);

        return $statement->rowCount();
    }
}
