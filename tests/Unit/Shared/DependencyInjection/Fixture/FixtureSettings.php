<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

use App\Shared\DependencyInjection\Env;

final class FixtureSettings
{
    public function __construct(
        #[Env('FIXTURE_NAME')] public readonly string $name,
        #[Env('FIXTURE_LIMIT')] public readonly int $limit,
        public readonly int $retries = 3,
    ) {
    }
}
