<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use Attribute;

/**
 * Injects a constructor parameter from the environment variable with the given name;
 * string parameters receive the value and int parameters a validated positive integer.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Env
{
    public function __construct(public string $key)
    {
    }
}
