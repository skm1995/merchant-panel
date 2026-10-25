<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use JsonException;

/**
 * Cached value with what XFetch needs: how long it took to compute and when it expires.
 */
final readonly class CacheEntry
{
    private const string VALUE_KEY = 'value';
    private const string DELTA_KEY = 'delta';
    private const string EXPIRES_AT_KEY = 'expires_at';

    public function __construct(public string $value, public float $delta, public float $expiresAt)
    {
    }

    public static function decode(string $encoded): ?self
    {
        try {
            $data = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (
            !is_array($data)
            || !is_string($data[self::VALUE_KEY] ?? null)
            || !is_numeric($data[self::DELTA_KEY] ?? null)
            || !is_numeric($data[self::EXPIRES_AT_KEY] ?? null)
        ) {
            return null;
        }

        return new self($data[self::VALUE_KEY], (float) $data[self::DELTA_KEY], (float) $data[self::EXPIRES_AT_KEY]);
    }

    public function encode(): string
    {
        return json_encode([
            self::VALUE_KEY => $this->value,
            self::DELTA_KEY => $this->delta,
            self::EXPIRES_AT_KEY => $this->expiresAt,
        ], JSON_THROW_ON_ERROR);
    }
}
