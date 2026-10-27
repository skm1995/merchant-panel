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
    private const string GRANT_MERCHANT_QUERY = 'UPDATE users SET merchant_since = :granted_at WHERE email = :email AND email_verified_at IS NOT NULL AND merchant_since IS NULL';
    private const string GRANTED_AT_PARAMETER = 'granted_at';
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

    public function grantMerchantAccess(string $email, DateTimeImmutable $grantedAt): void
    {
        $this->pdo->prepare(self::GRANT_MERCHANT_QUERY)->execute([
            self::GRANTED_AT_PARAMETER => $grantedAt->format(self::DATE_TIME_FORMAT),
            self::EMAIL_PARAMETER => $email,
        ]);
    }
}
