<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Shared\Database\RecordFetcher;
use LogicException;
use PDO;
use PDOException;

final class PdoUserRepository implements UserRepositoryInterface
{
    private const string FIND_QUERY = 'SELECT email, password AS passwordHash, created_at AS createdAt, email_verified_at AS emailVerifiedAt FROM users WHERE email = :email';
    private const string INSERT_QUERY = 'INSERT INTO users (email, password, created_at) VALUES (:email, :password, CURRENT_TIMESTAMP)';
    private const string EMAIL_PARAMETER = 'email';
    private const string PASSWORD_PARAMETER = 'password';
    private const int DRIVER_ERROR_CODE_INDEX = 1;
    private const int DUPLICATE_KEY_ERROR_CODE = 1062;
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
            if (($exception->errorInfo[self::DRIVER_ERROR_CODE_INDEX] ?? null) === self::DUPLICATE_KEY_ERROR_CODE) {
                throw new DuplicateEmailException($exception);
            }

            throw $exception;
        }

        return $this->findByEmail($email) ?? throw new LogicException(self::NOT_STORED_MESSAGE);
    }
}
