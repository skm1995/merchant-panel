<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

final class FixtureService
{
    public function __construct(public readonly FixtureStoreInterface $store, public readonly FixtureClock $clock)
    {
    }
}
