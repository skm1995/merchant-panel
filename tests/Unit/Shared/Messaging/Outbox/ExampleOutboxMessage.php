<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Outbox;

use App\Shared\Messaging\Outbox\OutboxMessageInterface;

final readonly class ExampleOutboxMessage implements OutboxMessageInterface
{
    private const string TYPE = 'test.example';
    private const string REFERENCE_KEY = 'reference';

    public function __construct(public string $reference)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data[self::REFERENCE_KEY]);
    }

    public function toArray(): array
    {
        return [self::REFERENCE_KEY => $this->reference];
    }
}
