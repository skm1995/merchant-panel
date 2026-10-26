<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection;

use App\Shared\DependencyInjection\ClassFinder;
use App\Shared\DependencyInjection\Container;
use App\Shared\DependencyInjection\Exception\ContainerException;
use App\Shared\DependencyInjection\ServiceDiscovery;
use PHPUnit\Framework\TestCase;
use Stringable;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\AmbiguousInterface;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\CachingRepository;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\ChannelInterface;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\DatabaseRepository;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\ExternalConnection;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\OnlySingleStore;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\RepositoryInterface;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\Runtime;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\SelfBuildingSettings;
use Tests\Unit\Shared\DependencyInjection\Fixture\Services\SingleStoreInterface;

final class ServiceDiscoveryTest extends TestCase
{
    private const string NAMESPACE = 'Tests\\Unit\\Shared\\DependencyInjection\\Fixture\\Services';
    private const string DSN_VARIABLE = 'FIXTURE_DSN';
    private const string DSN = 'fixture://connection';

    private ServiceDiscovery $discovery;

    protected function setUp(): void
    {
        $this->discovery = ServiceDiscovery::fromClasses(ClassFinder::find(__DIR__ . '/Fixture/Services', self::NAMESPACE));
        putenv(self::DSN_VARIABLE . '=' . self::DSN);
    }

    protected function tearDown(): void
    {
        putenv(self::DSN_VARIABLE);
    }

    public function testBindsInterfacesWithASingleImplementation(): void
    {
        self::assertInstanceOf(OnlySingleStore::class, $this->container()->get(SingleStoreInterface::class));
    }

    public function testLeavesInterfacesFromOutsideTheScannedSourceToFactories(): void
    {
        self::assertArrayNotHasKey(Stringable::class, $this->discovery->describe());
    }

    public function testPrefersTheDefaultImplementationAmongSeveral(): void
    {
        $repository = $this->container()->get(RepositoryInterface::class);

        self::assertInstanceOf(CachingRepository::class, $repository);
        self::assertInstanceOf(DatabaseRepository::class, $repository->inner);
    }

    public function testExplainsAmbiguousInterfacesWhenTheyAreRequested(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('FirstAmbiguous, ' . self::NAMESPACE . '\\SecondAmbiguous');

        $this->container()->get(AmbiguousInterface::class);
    }

    public function testBuildsServicesThroughStaticAndInstanceFactories(): void
    {
        $container = $this->container();

        self::assertSame(self::DSN, $container->get(ExternalConnection::class)->dsn);
        self::assertSame($container->get(Runtime::class), $container->get(ChannelInterface::class)->runtime());
    }

    public function testResolvesSelfReturnTypesToTheDeclaringClass(): void
    {
        self::assertSame('named constructor', $this->container()->get(SelfBuildingSettings::class)->source);
    }

    public function testDescribesWhereEveryBindingComesFrom(): void
    {
        $sources = $this->discovery->describe();

        self::assertSame('single implementation ' . OnlySingleStore::class, $sources[SingleStoreInterface::class]);
        self::assertSame('#[DefaultImplementation] ' . CachingRepository::class, $sources[RepositoryInterface::class]);
        self::assertStringStartsWith('ambiguous: ', $sources[AmbiguousInterface::class]);
        self::assertSame('#[ServiceFactory] ' . Runtime::class . '::channel()', $sources[ChannelInterface::class]);
    }

    public function testSurvivesAnExportImportRoundTrip(): void
    {
        $restored = ServiceDiscovery::fromArray($this->discovery->toArray());

        self::assertSame($this->discovery->describe(), $restored->describe());
    }

    private function container(): Container
    {
        return new Container($this->discovery->definitions());
    }
}
