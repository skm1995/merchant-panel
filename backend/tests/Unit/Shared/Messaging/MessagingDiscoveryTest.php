<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging;

use App\Shared\DependencyInjection\ClassFinder;
use App\Shared\DependencyInjection\Container;
use App\Shared\Messaging\MessagingDiscovery;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\Messaging\Fixture\Discovery\ExampleDirectMessage;
use Tests\Unit\Shared\Messaging\Fixture\Discovery\ExampleMessageHandler;
use Tests\Unit\Shared\Messaging\Fixture\Discovery\ExampleRoutedMessage;

final class MessagingDiscoveryTest extends TestCase
{
    private const string NAMESPACE = 'Tests\\Unit\\Shared\\Messaging\\Fixture\\Discovery';

    private MessagingDiscovery $discovery;

    protected function setUp(): void
    {
        $this->discovery = new MessagingDiscovery(ClassFinder::find(__DIR__ . '/Fixture/Discovery', self::NAMESPACE));
    }

    public function testRoutesMessagesToTheTransportNamedOnTheClass(): void
    {
        self::assertSame(
            [ExampleDirectMessage::class => 'direct', ExampleRoutedMessage::class => 'examples'],
            $this->discovery->routes(),
        );
    }

    public function testDerivesTransportDsnVariablesFromTransportNames(): void
    {
        self::assertSame(
            ['direct' => 'MESSENGER_DIRECT_DSN', 'examples' => 'MESSENGER_EXAMPLES_DSN', 'failed' => 'MESSENGER_FAILED_DSN'],
            $this->discovery->transportDsnKeys(),
        );
    }

    public function testListsOutboxMessagesByTheirInterface(): void
    {
        self::assertSame([ExampleRoutedMessage::class], $this->discovery->outboxMessages());
    }

    public function testBuildsHandlersFromAttributedMethodsThroughTheContainer(): void
    {
        $container = new Container();
        $handlers = $this->discovery->handlers($container);
        $message = new ExampleRoutedMessage();

        self::assertSame([ExampleRoutedMessage::class], array_keys($handlers));
        ($handlers[ExampleRoutedMessage::class][0])($message);
        self::assertSame([$message], $container->get(ExampleMessageHandler::class)->handled);
    }
}
