<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use PDO;

final class TestUserData
{
    private const string EMAIL_FORMAT = '%s%s@example.com';
    private const int RANDOM_SUFFIX_BYTES = 8;
    private const string EMAIL_PARAMETER = 'email';
    private const string PASSWORD_PARAMETER = 'password';
    private const string CREATE_VERIFIED_QUERY = 'INSERT INTO users (email, password, created_at, email_verified_at) VALUES (:email, :password, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
    private const string CREATE_MERCHANT_QUERY = 'INSERT INTO users (email, password, created_at, email_verified_at, merchant_since) VALUES (:email, :password, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
    private const array CLEANUP_QUERIES = [
        'DELETE FROM email_verification_tokens WHERE email = :email',
        'DELETE FROM users WHERE email = :email',
    ];

    public static function uniqueEmail(string $prefix): string
    {
        return sprintf(self::EMAIL_FORMAT, $prefix, random_bytes(self::RANDOM_SUFFIX_BYTES) |> bin2hex(...));
    }

    /**
     * Creates a user whose email address is already confirmed.
     */
    public static function createVerified(PDO $pdo, string $email, string $password): void
    {
        $pdo->prepare(self::CREATE_VERIFIED_QUERY)->execute([
            self::EMAIL_PARAMETER => $email,
            self::PASSWORD_PARAMETER => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * Creates a confirmed user with access to the merchant panel.
     */
    public static function createMerchant(PDO $pdo, string $email, string $password): void
    {
        $pdo->prepare(self::CREATE_MERCHANT_QUERY)->execute([
            self::EMAIL_PARAMETER => $email,
            self::PASSWORD_PARAMETER => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public static function delete(PDO $pdo, string $email): void
    {
        foreach (self::CLEANUP_QUERIES as $query) {
            $pdo->prepare($query)->execute([self::EMAIL_PARAMETER => $email]);
        }
    }
}
