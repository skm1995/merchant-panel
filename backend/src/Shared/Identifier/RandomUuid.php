<?php

declare(strict_types=1);

namespace App\Shared\Identifier;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

/**
 * Unguessable identifier backed by a random UUID version 4 (122 random bits), for ids
 * that grant access to a resource in the sessionless API. Stored as BINARY(16).
 */
abstract readonly class RandomUuid
{
    public const string PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
    private const string INVALID_MESSAGE = 'An identifier must be a canonical lowercase version 4 UUID.';

    final protected function __construct(private UuidV4 $uuid, public string $value)
    {
    }

    public static function generate(): static
    {
        return self::fromUuid(Uuid::v4());
    }

    public static function fromString(string $value): static
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(self::INVALID_MESSAGE);
        }

        $id = self::fromUuid(Uuid::fromString($value));

        return $id->value === $value ? $id : throw new InvalidArgumentException(self::INVALID_MESSAGE);
    }

    public static function fromBinary(string $bytes): static
    {
        return self::fromUuid(Uuid::fromBinary($bytes));
    }

    public function toBinary(): string
    {
        return $this->uuid->toBinary();
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }

    private static function fromUuid(Uuid $uuid): static
    {
        if (!$uuid instanceof UuidV4) {
            throw new InvalidArgumentException(self::INVALID_MESSAGE);
        }

        return new static($uuid, $uuid->toRfc4122());
    }
}
