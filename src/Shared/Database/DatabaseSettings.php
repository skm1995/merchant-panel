<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Shared\Config\Environment;

final readonly class DatabaseSettings
{
    public const string CHARSET = 'utf8mb4';
    private const string DSN_FORMAT = 'mysql:host=%s;port=%d;dbname=%s;charset=%s';

    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            Environment::get(DatabaseEnvironmentEnum::HOST->value),
            (int) Environment::get(DatabaseEnvironmentEnum::PORT->value),
            Environment::get(DatabaseEnvironmentEnum::DATABASE->value),
            Environment::get(DatabaseEnvironmentEnum::USERNAME->value),
            Environment::get(DatabaseEnvironmentEnum::PASSWORD->value),
        );
    }

    public function dsn(): string
    {
        return sprintf(self::DSN_FORMAT, $this->host, $this->port, $this->database, self::CHARSET);
    }
}
