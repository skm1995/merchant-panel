<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection;

use App\Shared\DependencyInjection\Container;
use App\Shared\DependencyInjection\Exception\ContainerException;
use App\Shared\DependencyInjection\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\DependencyInjection\Fixture\CachingFixtureStore;
use Tests\Unit\Shared\DependencyInjection\Fixture\CircularFirst;
use Tests\Unit\Shared\DependencyInjection\Fixture\FixtureClock;
use Tests\Unit\Shared\DependencyInjection\Fixture\FixtureService;
use Tests\Unit\Shared\DependencyInjection\Fixture\FixtureSettings;
use Tests\Unit\Shared\DependencyInjection\Fixture\FixtureStoreInterface;
use Tests\Unit\Shared\DependencyInjection\Fixture\FixtureWithOptionalCallback;
use Tests\Unit\Shared\DependencyInjection\Fixture\InMemoryFixtureStore;
use Tests\Unit\Shared\DependencyInjection\Fixture\NeedsUnconfiguredScalar;

final class ContainerTest extends TestCase
{
    private const string NAME_VARIABLE = 'FIXTURE_NAME';
    private const string LIMIT_VARIABLE = 'FIXTURE_LIMIT';
    private const string ASSIGNMENT_FORMAT = '%s=%s';

    protected function tearDown(): void
    {
        putenv(self::NAME_VARIABLE);
        putenv(self::LIMIT_VARIABLE);
    }

    public function testAutowiresNestedDependenciesAndSharesInstances(): void
    {
        $container = new Container([FixtureStoreInterface::class => InMemoryFixtureStore::class]);

        $service = $container->get(FixtureService::class);

        self::assertInstanceOf(InMemoryFixtureStore::class, $service->store);
        self::assertSame($service->clock, $container->get(FixtureClock::class));
        self::assertSame($service, $container->get(FixtureService::class));
    }

    public function testFactoriesBuildServicesThatNeedCustomConstruction(): void
    {
        $clock = new FixtureClock();
        $container = new Container([FixtureClock::class => static fn (): FixtureClock => $clock]);

        self::assertSame($clock, $container->get(FixtureClock::class));
    }

    public function testDecoratorReceivesTheImplementationNamedByAutowire(): void
    {
        $container = new Container([FixtureStoreInterface::class => CachingFixtureStore::class]);

        $store = $container->get(FixtureStoreInterface::class);

        self::assertInstanceOf(CachingFixtureStore::class, $store);
        self::assertInstanceOf(InMemoryFixtureStore::class, $store->inner);
    }

    public function testReadsScalarsFromTheEnvironmentAndKeepsDefaults(): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::NAME_VARIABLE, 'catalog'));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, self::LIMIT_VARIABLE, '25'));

        $settings = new Container()->get(FixtureSettings::class);

        self::assertSame('catalog', $settings->name);
        self::assertSame(25, $settings->limit);
        self::assertSame(3, $settings->retries);
    }

    public function testKeepsDefaultsForOptionalTypesItCannotBuild(): void
    {
        $service = new Container()->get(FixtureWithOptionalCallback::class);

        self::assertNull($service->callback);
    }

    public function testReportsInterfacesWithoutBinding(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(FixtureStoreInterface::class);

        new Container()->get(FixtureService::class);
    }

    public function testReportsScalarsWithoutConfiguration(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('$name');

        new Container()->get(NeedsUnconfiguredScalar::class);
    }

    public function testReportsCircularDependencies(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency');

        new Container()->get(CircularFirst::class);
    }

    public function testReportsUnknownServices(): void
    {
        $container = new Container();

        self::assertTrue($container->has(FixtureClock::class));
        self::assertFalse($container->has('App\\Missing\\Service'));
        $this->expectException(ServiceNotFoundException::class);
        $container->get('App\\Missing\\Service');
    }
}
