<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Config\Exception\InvalidEnvironmentVariableException;
use App\Shared\Http\RateLimitPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RateLimitPolicyTest extends TestCase
{
    private const string VARIABLE = 'RATE_LIMIT_POLICY_TEST';
    private const string ASSIGNMENT_FORMAT = '%s=%s';

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);
    }

    public function testReadsTheLimitAndItsWindowFromTheEnvironment(): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::VARIABLE, '10/300'));

        $policy = RateLimitPolicy::fromEnvironment(self::VARIABLE);

        self::assertSame(10, $policy->limit);
        self::assertSame(300, $policy->windowSeconds);
    }

    /** @return array<string, array{string}> */
    public static function invalidPolicies(): array
    {
        return [
            'no window' => ['10'],
            'zero requests' => ['0/60'],
            'zero seconds' => ['10/0'],
            'not numbers' => ['ten/minute'],
            'extra part' => ['10/60/5'],
        ];
    }

    #[DataProvider('invalidPolicies')]
    public function testRejectsAPolicyThatIsNotRequestsPerSeconds(string $value): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::VARIABLE, $value));

        $this->expectException(InvalidEnvironmentVariableException::class);
        RateLimitPolicy::fromEnvironment(self::VARIABLE);
    }
}
