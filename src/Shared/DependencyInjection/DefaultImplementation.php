<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use Attribute;

/**
 * Chooses this class for each interface it implements when several classes implement it,
 * for example a caching decorator over a database repository.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DefaultImplementation
{
}
