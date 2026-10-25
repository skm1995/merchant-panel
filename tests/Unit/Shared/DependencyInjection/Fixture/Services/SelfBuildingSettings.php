<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

use App\Shared\DependencyInjection\ServiceFactory;

final class SelfBuildingSettings
{
    private const string SOURCE = 'named constructor';

    private function __construct(public readonly string $source)
    {
    }

    #[ServiceFactory]
    public static function create(): self
    {
        return new self(self::SOURCE);
    }
}
