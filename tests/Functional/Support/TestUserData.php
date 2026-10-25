<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use PDO;

final class TestUserData
{
    private const string EMAIL_FORMAT = '%s%s@example.com';
    private const int RANDOM_SUFFIX_BYTES = 8;
    private const string EMAIL_PARAMETER = 'email';
    private const array CLEANUP_QUERIES = [
        'DELETE FROM email_verification_tokens WHERE email = :email',
        'DELETE FROM users WHERE email = :email',
    ];

    public static function uniqueEmail(string $prefix): string
    {
        return sprintf(self::EMAIL_FORMAT, $prefix, random_bytes(self::RANDOM_SUFFIX_BYTES) |> bin2hex(...));
    }

    public static function delete(PDO $pdo, string $email): void
    {
        foreach (self::CLEANUP_QUERIES as $query) {
            $pdo->prepare($query)->execute([self::EMAIL_PARAMETER => $email]);
        }
    }
}
