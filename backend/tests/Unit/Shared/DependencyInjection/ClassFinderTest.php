<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection;

use App\Shared\DependencyInjection\ClassFinder;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\Http\Fixture\Discovery\ExampleController;
use Tests\Unit\Shared\Http\Fixture\Discovery\ExampleHelperWithoutRoutes;

final class ClassFinderTest extends TestCase
{
    private const string NAMESPACE = 'Tests\\Unit\\Shared\\Http\\Fixture\\Discovery';

    public function testMapsFilesToClassNamesFollowingPsr4(): void
    {
        self::assertSame(
            [ExampleController::class, ExampleHelperWithoutRoutes::class],
            ClassFinder::find(dirname(__DIR__) . '/Http/Fixture/Discovery', self::NAMESPACE),
        );
    }
}
