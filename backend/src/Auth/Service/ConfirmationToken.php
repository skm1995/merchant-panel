<?php

declare(strict_types=1);

namespace App\Auth\Service;

final readonly class ConfirmationToken
{
    private const int RANDOM_BYTES = 32;
    private const string HASH_ALGORITHM = 'sha256';

    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return random_bytes(self::RANDOM_BYTES)
            |> bin2hex(...)
            |> self::fromString(...);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function hash(): string
    {
        return hash(self::HASH_ALGORITHM, $this->value);
    }
}
