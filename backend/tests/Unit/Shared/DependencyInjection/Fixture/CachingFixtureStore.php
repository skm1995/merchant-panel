<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

use App\Shared\DependencyInjection\Autowire;

final class CachingFixtureStore implements FixtureStoreInterface
{
    public function __construct(#[Autowire(InMemoryFixtureStore::class)] public readonly FixtureStoreInterface $inner)
    {
    }
}
