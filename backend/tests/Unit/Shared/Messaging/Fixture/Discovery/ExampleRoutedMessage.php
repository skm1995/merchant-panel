<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Discovery;

use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

#[Message(transport: 'examples')]
final readonly class ExampleRoutedMessage implements OutboxMessageInterface
{
    private const string TYPE = 'test.discovered';

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
