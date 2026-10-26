<?php

declare(strict_types=1);

namespace App\Shared\Serialization;

use JsonException;

final class JsonDecoder
{
    /**
     * Decodes a JSON object or array; anything else, including invalid JSON, is null.
     *
     * @return array<mixed>|null
     */
    public static function toArray(string $json): ?array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
