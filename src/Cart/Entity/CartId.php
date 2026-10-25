<?php

declare(strict_types=1);

namespace App\Cart\Entity;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

/**
 * Unguessable cart identifier: a random UUID version 4 with 122 random bits, because
 * knowing the id grants access to the cart. Stored as BINARY(16).
 */
final readonly class CartId
{
    public const string PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
    private const string INVALID_MESSAGE = 'A cart id must be a canonical lowercase version 4 UUID.';

    private function __construct(private UuidV4 $uuid, public string $value)
    {
    }

    public static function generate(): self
    {
        return self::fromUuid(Uuid::v4());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(self::INVALID_MESSAGE);
        }

        $id = self::fromUuid(Uuid::fromString($value));

        return $id->value === $value ? $id : throw new InvalidArgumentException(self::INVALID_MESSAGE);
    }

    public static function fromBinary(string $bytes): self
    {
        return self::fromUuid(Uuid::fromBinary($bytes));
    }

    public function toBinary(): string
    {
        return $this->uuid->toBinary();
    }

    private static function fromUuid(Uuid $uuid): self
    {
        if (!$uuid instanceof UuidV4) {
            throw new InvalidArgumentException(self::INVALID_MESSAGE);
        }

        return new self($uuid, $uuid->toRfc4122());
    }
}
