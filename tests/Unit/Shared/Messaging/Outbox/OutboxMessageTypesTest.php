<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Outbox;

use App\Shared\Messaging\Exception\UnknownOutboxMessageTypeException;
use App\Shared\Messaging\Outbox\OutboxMessageTypes;
use LogicException;
use PHPUnit\Framework\TestCase;

final class OutboxMessageTypesTest extends TestCase
{
    private const string REFERENCE = 'ref-1';
    private const string UNKNOWN_TYPE = 'test.unknown';

    public function testRestoresMessageFromItsStableTypeName(): void
    {
        $types = new OutboxMessageTypes([ExampleOutboxMessage::class]);

        $message = $types->restore(ExampleOutboxMessage::messageType(), ['reference' => self::REFERENCE]);

        self::assertInstanceOf(ExampleOutboxMessage::class, $message);
        self::assertSame(self::REFERENCE, $message->reference);
    }

    public function testRejectsUnknownTypes(): void
    {
        $this->expectException(UnknownOutboxMessageTypeException::class);

        new OutboxMessageTypes([ExampleOutboxMessage::class])->restore(self::UNKNOWN_TYPE, []);
    }

    public function testRejectsTwoClassesSharingAType(): void
    {
        $this->expectException(LogicException::class);

        new OutboxMessageTypes([ExampleOutboxMessage::class, ExampleOutboxMessage::class]);
    }
}
