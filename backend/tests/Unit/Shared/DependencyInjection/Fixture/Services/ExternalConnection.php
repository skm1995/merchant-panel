<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

/**
 * Stands in for a vendor class the application cannot annotate.
 */
final class ExternalConnection
{
    public function __construct(public readonly string $dsn)
    {
    }
}
