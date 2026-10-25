<?php

declare(strict_types=1);

namespace Tests\Functional\DependencyInjection;

use App\Catalog\Infrastructure\Cache\CachedProductRepository;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Http\ControllerDiscovery;
use App\Shared\Http\HttpApplication;
use App\Shared\Messaging\MessagingDiscovery;
use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Shared\Messaging\Outbox\OutboxRelay;
use App\Shared\Messaging\Outbox\OutboxStoreInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Builds every discovered controller and message handler from config/services.php, so a
 * missing binding, environment variable, or handler fails here instead of at runtime.
 */
final class ServiceGraphTest extends TestCase
{
    /** @return iterable<string, array{class-string}> */
    public static function entryPoints(): iterable
    {
        foreach ([...ControllerDiscovery::fromSource(), HttpApplication::class, OutboxRelay::class] as $class) {
            yield $class => [$class];
        }
    }

    public function testContainerBuildsEveryDiscoveredMessageHandler(): void
    {
        $container = ContainerFactory::create();

        $handlers = $container->get(MessagingDiscovery::class)->handlers($container);

        self::assertEqualsCanonicalizing(
            array_keys($container->get(MessagingDiscovery::class)->routes()),
            array_keys($handlers),
            'Every routed message needs a handler.',
        );
    }

    /** @param class-string $class */
    #[DataProvider('entryPoints')]
    public function testContainerBuildsEntryPoint(string $class): void
    {
        self::assertInstanceOf($class, ContainerFactory::create()->get($class));
    }

    public function testBindingsShareInstancesAndDecorateTheCatalog(): void
    {
        $container = ContainerFactory::create();

        self::assertInstanceOf(CachedProductRepository::class, $container->get(ProductRepositoryInterface::class));
        self::assertSame($container->get(OutboxInterface::class), $container->get(OutboxStoreInterface::class));
    }
}
