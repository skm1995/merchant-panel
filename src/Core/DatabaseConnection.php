<?php

declare(strict_types=1);

namespace App\Core;

use App\Enum\DatabaseEnvironmentEnum;
use PDO;

final class DatabaseConnection
{
    private const string DEFAULT_HOST = 'mysql';
    private const string DEFAULT_PORT = '3306';
    private const string DEFAULT_DATABASE = 'app';
    private const string DEFAULT_USERNAME = 'app';
    private const string DEFAULT_PASSWORD = 'app';
    private const string DSN_FORMAT = 'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4';

    public static function createFromEnvironment(): PDO
    {
        $dsn = sprintf(
            self::DSN_FORMAT,
            self::environment(DatabaseEnvironmentEnum::HOST, self::DEFAULT_HOST),
            self::environment(DatabaseEnvironmentEnum::PORT, self::DEFAULT_PORT),
            self::environment(DatabaseEnvironmentEnum::DATABASE, self::DEFAULT_DATABASE),
        );

        return new PDO(
            $dsn,
            self::environment(DatabaseEnvironmentEnum::USERNAME, self::DEFAULT_USERNAME),
            self::environment(DatabaseEnvironmentEnum::PASSWORD, self::DEFAULT_PASSWORD),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private static function environment(DatabaseEnvironmentEnum $key, string $default): string
    {
        $value = getenv($key->value);

        return is_string($value) && strlen($value) > 0 ? $value : $default;
    }
}
