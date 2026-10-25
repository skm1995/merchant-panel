<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

use Closure;

final class FixtureWithOptionalCallback
{
    public function __construct(public readonly FixtureClock $clock, public readonly ?Closure $callback = null)
    {
    }
}
