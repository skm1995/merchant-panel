<?php

declare(strict_types=1);

namespace App\Shared\Database;

use Attribute;

/**
 * Marks a readonly record DTO whose properties are initialized by PDO::FETCH_CLASS
 * from the selected column aliases instead of by a constructor.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class HydratedRecord
{
}
