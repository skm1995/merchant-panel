<?php

declare(strict_types=1);

namespace App\Shared\Database;

use PDO;

final class DatabaseConnection
{
    public static function createFromEnvironment(): PDO
    {
        $settings = DatabaseSettings::fromEnvironment();

        return new PDO(
            $settings->dsn(),
            $settings->username,
            $settings->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
