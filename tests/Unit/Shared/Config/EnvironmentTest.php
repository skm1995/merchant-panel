<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Config;

use App\Shared\Config\Environment;
use App\Shared\Config\MissingEnvironmentVariableException;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private const string KEY = 'APP_ENVIRONMENT_TEST_VALUE';
    private const string VALUE = 'configured';
    private const string ASSIGNMENT_FORMAT = '%s=%s';
    private const string EMPTY_ASSIGNMENT_FORMAT = '%s=';

    protected function tearDown(): void
    {
        putenv(self::KEY);
    }

    public function testReturnsConfiguredValue(): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::KEY, self::VALUE));

        self::assertSame(self::VALUE, Environment::find(self::KEY));
        self::assertSame(self::VALUE, Environment::get(self::KEY));
    }

    public function testTreatsEmptyValueAsUnset(): void
    {
        putenv(sprintf(self::EMPTY_ASSIGNMENT_FORMAT, self::KEY));

        self::assertNull(Environment::find(self::KEY));
    }

    public function testRequiredValueMustBeConfigured(): void
    {
        $this->expectException(MissingEnvironmentVariableException::class);
        $this->expectExceptionMessage(self::KEY);

        Environment::get(self::KEY);
    }
}
