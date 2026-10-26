<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\DependencyInjection\ClassFinder;
use App\Shared\Http\ControllerDiscovery;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\Http\Fixture\Discovery\ExampleController;

final class ControllerDiscoveryTest extends TestCase
{
    private const string NAMESPACE = 'Tests\\Unit\\Shared\\Http\\Fixture\\Discovery';

    public function testFindsOnlyClassesWithRoutes(): void
    {
        self::assertSame(
            [ExampleController::class],
            ControllerDiscovery::filterControllers(ClassFinder::find(__DIR__ . '/Fixture/Discovery', self::NAMESPACE)),
        );
    }
}
