<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Exception\InvalidWebhookPayloadException;
use App\Shared\Serialization\JsonDecoder;

/**
 * Typed access to a decoded provider webhook body; any unexpected shape is a payload error.
 */
final readonly class WebhookPayload
{
    private const string PATH_SEPARATOR = '.';

    /** @param array<mixed> $data */
    private function __construct(private array $data)
    {
    }

    public static function decode(string $payload): self
    {
        return new self(JsonDecoder::toArray($payload) ?? throw new InvalidWebhookPayloadException());
    }

    /** @return non-empty-string */
    public function string(string $path): string
    {
        $value = $this->value($path);

        return is_string($value) && strlen($value) > 0 ? $value : throw new InvalidWebhookPayloadException();
    }

    public function integer(string $path): int
    {
        $value = $this->value($path);

        return is_int($value) ? $value : throw new InvalidWebhookPayloadException();
    }

    private function value(string $path): mixed
    {
        $value = $this->data;

        foreach (explode(self::PATH_SEPARATOR, $path) as $key) {
            $value = is_array($value) && array_key_exists($key, $value)
                ? $value[$key]
                : throw new InvalidWebhookPayloadException();
        }

        return $value;
    }
}
