<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

final class NeedsUnconfiguredScalar
{
    public function __construct(public readonly string $name)
    {
    }
}
