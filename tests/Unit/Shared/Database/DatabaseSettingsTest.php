<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Shared\Config\Exception\MissingEnvironmentVariableException;
use App\Shared\Database\Enum\DatabaseEnvironmentEnum;
use App\Shared\Database\DatabaseSettings;
use PHPUnit\Framework\TestCase;

final class DatabaseSettingsTest extends TestCase
{
    private const string HOST = 'database.test';
    private const string PORT = '3307';
    private const string DATABASE = 'settings_test';
    private const string USERNAME = 'settings_user';
    private const string PASSWORD = 'settings_password';
    private const string DSN = 'mysql:host=database.test;port=3307;dbname=settings_test;charset=utf8mb4';
    private const string ASSIGNMENT_FORMAT = '%s=%s';

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (DatabaseEnvironmentEnum::cases() as $key) {
            $this->originalEnvironment[$key->value] = getenv($key->value);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            putenv($value === false ? $key : sprintf(self::ASSIGNMENT_FORMAT, $key, $value));
        }
    }

    public function testBuildsMysqlDsnFromEnvironment(): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, DatabaseEnvironmentEnum::HOST->value, self::HOST));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, DatabaseEnvironmentEnum::PORT->value, self::PORT));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, DatabaseEnvironmentEnum::DATABASE->value, self::DATABASE));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, DatabaseEnvironmentEnum::USERNAME->value, self::USERNAME));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, DatabaseEnvironmentEnum::PASSWORD->value, self::PASSWORD));

        $settings = DatabaseSettings::fromEnvironment();

        self::assertSame(self::DSN, $settings->dsn());
        self::assertSame(self::HOST, $settings->host);
        self::assertSame((int) self::PORT, $settings->port);
        self::assertSame(self::USERNAME, $settings->username);
        self::assertSame(self::PASSWORD, $settings->password);
    }

    public function testRequiresDatabaseHostInEnvironment(): void
    {
        putenv(DatabaseEnvironmentEnum::HOST->value);

        $this->expectException(MissingEnvironmentVariableException::class);
        DatabaseSettings::fromEnvironment();
    }
}
