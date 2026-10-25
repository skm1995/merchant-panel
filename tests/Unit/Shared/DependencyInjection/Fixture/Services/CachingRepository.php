<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

use App\Shared\DependencyInjection\Autowire;
use App\Shared\DependencyInjection\DefaultImplementation;

#[DefaultImplementation]
final class CachingRepository implements RepositoryInterface
{
    public function __construct(#[Autowire(DatabaseRepository::class)] public readonly RepositoryInterface $inner)
    {
    }
}
