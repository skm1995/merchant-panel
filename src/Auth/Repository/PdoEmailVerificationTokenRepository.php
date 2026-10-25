<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Shared\Database\RecordFetcher;
use DateTimeImmutable;
use PDO;
use Throwable;

final class PdoEmailVerificationTokenRepository implements EmailVerificationTokenRepositoryInterface
{
    private const string STORE_QUERY = 'INSERT INTO email_verification_tokens (email, token_hash, expires_at) VALUES (:email, :token_hash, :expires_at) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)';
    private const string RESEND_STATE_QUERY = 'SELECT resend_count AS resendCount, resend_window_started_at AS resendWindowStartedAt FROM email_verification_tokens WHERE email = :email FOR UPDATE';
    private const string INSERT_RESEND_QUERY = 'INSERT INTO email_verification_tokens (email, token_hash, expires_at, resend_count, resend_window_started_at) VALUES (:email, :token_hash, :expires_at, 1, :window_started_at)';
    private const string UPDATE_RESEND_QUERY = 'UPDATE email_verification_tokens SET token_hash = :token_hash, expires_at = :expires_at, resend_count = :resend_count, resend_window_started_at = :window_started_at WHERE email = :email';
    private const string FIND_VALID_TOKEN_QUERY = 'SELECT email FROM email_verification_tokens WHERE token_hash = :token_hash AND expires_at > :confirmed_at FOR UPDATE';
    private const string VERIFY_USER_QUERY = 'UPDATE users SET email_verified_at = :confirmed_at WHERE email = :email AND email_verified_at IS NULL';
    private const string DELETE_TOKEN_QUERY = 'DELETE FROM email_verification_tokens WHERE token_hash = :token_hash';
    private const string EMAIL_PARAMETER = 'email';
    private const string TOKEN_HASH_PARAMETER = 'token_hash';
    private const string EXPIRES_AT_PARAMETER = 'expires_at';
    private const string CONFIRMED_AT_PARAMETER = 'confirmed_at';
    private const string RESEND_COUNT_PARAMETER = 'resend_count';
    private const string WINDOW_STARTED_AT_PARAMETER = 'window_started_at';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const string WINDOW_START_MODIFIER_FORMAT = '-%d seconds';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function store(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $this->pdo->prepare(self::STORE_QUERY)->execute([
            self::EMAIL_PARAMETER => $email,
            self::TOKEN_HASH_PARAMETER => $tokenHash,
            self::EXPIRES_AT_PARAMETER => $expiresAt->format(self::DATETIME_FORMAT),
        ]);
    }

    public function storeWithinResendLimit(
        string $email,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
        int $windowSeconds,
        int $maximumAttempts,
    ): bool {
        return $this->transactional(function () use (
            $email,
            $tokenHash,
            $expiresAt,
            $now,
            $windowSeconds,
            $maximumAttempts,
        ): bool {
            $stateStatement = $this->pdo->prepare(self::RESEND_STATE_QUERY);
            $stateStatement->execute([self::EMAIL_PARAMETER => $email]);
            $state = RecordFetcher::one($stateStatement, ResendStateRecord::class);

            if ($state === null) {
                $this->pdo->prepare(self::INSERT_RESEND_QUERY)->execute([
                    self::EMAIL_PARAMETER => $email,
                    self::TOKEN_HASH_PARAMETER => $tokenHash,
                    self::EXPIRES_AT_PARAMETER => $expiresAt->format(self::DATETIME_FORMAT),
                    self::WINDOW_STARTED_AT_PARAMETER => $now->format(self::DATETIME_FORMAT),
                ]);

                return true;
            }

            $windowStartedAt = $state->windowStartedAt();
            $windowExpired = $windowStartedAt <= $now->modify(
                sprintf(self::WINDOW_START_MODIFIER_FORMAT, $windowSeconds),
            );
            $resendCount = $windowExpired ? 0 : $state->resendCount;

            if ($resendCount >= $maximumAttempts) {
                return false;
            }

            $this->pdo->prepare(self::UPDATE_RESEND_QUERY)->execute([
                self::EMAIL_PARAMETER => $email,
                self::TOKEN_HASH_PARAMETER => $tokenHash,
                self::EXPIRES_AT_PARAMETER => $expiresAt->format(self::DATETIME_FORMAT),
                self::RESEND_COUNT_PARAMETER => $resendCount + 1,
                self::WINDOW_STARTED_AT_PARAMETER => ($windowExpired ? $now : $windowStartedAt)
                    ->format(self::DATETIME_FORMAT),
            ]);

            return true;
        });
    }

    public function confirmEmail(string $tokenHash, DateTimeImmutable $confirmedAt): bool
    {
        return $this->transactional(function () use ($tokenHash, $confirmedAt): bool {
            $confirmedAtValue = $confirmedAt->format(self::DATETIME_FORMAT);
            $findStatement = $this->pdo->prepare(self::FIND_VALID_TOKEN_QUERY);
            $findStatement->execute([
                self::TOKEN_HASH_PARAMETER => $tokenHash,
                self::CONFIRMED_AT_PARAMETER => $confirmedAtValue,
            ]);
            $email = $findStatement->fetchColumn();

            if (!is_string($email)) {
                return false;
            }

            $verifyStatement = $this->pdo->prepare(self::VERIFY_USER_QUERY);
            $verifyStatement->execute([
                self::CONFIRMED_AT_PARAMETER => $confirmedAtValue,
                self::EMAIL_PARAMETER => $email,
            ]);
            $this->pdo->prepare(self::DELETE_TOKEN_QUERY)->execute([self::TOKEN_HASH_PARAMETER => $tokenHash]);

            return $verifyStatement->rowCount() === 1;
        });
    }

    /**
     * @param callable(): bool $operation
     */
    private function transactional(callable $operation): bool
    {
        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
