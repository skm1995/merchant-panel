<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging;

use App\Shared\Messaging\RoutingSendersLocator;
use App\Shared\Messaging\RuntimeServiceContainer;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final class RoutingSendersLocatorTest extends TestCase
{
    private const string TRANSPORT = 'emails';

    public function testRoutesMessageClassToItsNamedTransport(): void
    {
        $sender = $this->createStub(SenderInterface::class);
        $locator = new RoutingSendersLocator(
            [RoutedMessage::class => self::TRANSPORT],
            new RuntimeServiceContainer([self::TRANSPORT => $sender]),
        );

        self::assertSame(
            [self::TRANSPORT => $sender],
            iterator_to_array($locator->getSenders(new Envelope(new RoutedMessage()))),
        );
    }

    public function testLeavesUnroutedMessagesWithoutSenders(): void
    {
        $locator = new RoutingSendersLocator([], new RuntimeServiceContainer([]));

        self::assertSame([], iterator_to_array($locator->getSenders(new Envelope(new stdClass()))));
    }
}
