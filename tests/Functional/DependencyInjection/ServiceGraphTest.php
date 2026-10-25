<?php

declare(strict_types=1);

namespace Tests\Functional\DependencyInjection;

use App\Auth\Infrastructure\Messenger\SendConfirmationEmailHandler;
use App\Catalog\Infrastructure\Cache\CachedProductRepository;
use App\Catalog\Infrastructure\Cache\ProductCache;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Order\Command\ExpireOrderReservationHandler;
use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Shared\Messaging\Outbox\OutboxRelay;
use App\Shared\Messaging\Outbox\OutboxStoreInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Builds every entry point from config/services.php, so a missing binding or environment
 * variable fails here instead of at runtime.
 */
final class ServiceGraphTest extends TestCase
{
    private const string CONTROLLER_PATTERN = '%s/src/*/Controller/*Controller.php';
    private const string SOURCE_PREFIX = '%s/src/';
    private const string NAMESPACE_PREFIX = 'App\\';
    private const string PHP_EXTENSION = '.php';

    /** @return iterable<string, array{class-string}> */
    public static function entryPoints(): iterable
    {
        $root = dirname(__DIR__, 3);

        foreach (glob(sprintf(self::CONTROLLER_PATTERN, $root)) ?: [] as $file) {
            /** @var class-string $class */
            $class = self::NAMESPACE_PREFIX . str_replace(
                ['/', self::PHP_EXTENSION],
                ['\\', ''],
                substr($file, strlen(sprintf(self::SOURCE_PREFIX, $root))),
            );

            yield $class => [$class];
        }

        foreach ([SendConfirmationEmailHandler::class, ExpireOrderReservationHandler::class, ProductCache::class, OutboxRelay::class] as $class) {
            yield $class => [$class];
        }
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
