<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use Attribute;

/**
 * Injects the named service instead of the one bound to the parameter type, for example
 * the concrete repository a decorator wraps.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Autowire
{
    /** @param class-string $service */
    public function __construct(public string $service)
    {
    }
}
