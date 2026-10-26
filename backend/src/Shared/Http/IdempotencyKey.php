<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Exception\InvalidRequestException;

/**
 * Reads the Idempotency-Key header that clients send so a retried request is applied once.
 */
final class IdempotencyKey
{
    public const int MAXIMUM_LENGTH = 255;
    private const string PRINTABLE_KEY_PATTERN = '/^[\x21-\x7e]{1,' . self::MAXIMUM_LENGTH . '}$/';
    private const string INVALID_KEY_MESSAGE = 'The Idempotency-Key header is required and must contain 1 to ' . self::MAXIMUM_LENGTH . ' printable ASCII characters.';

    public static function parse(Request $request): string
    {
        $key = $request->header(HeaderNameEnum::IDEMPOTENCY_KEY->value);

        if ($key === null || preg_match(self::PRINTABLE_KEY_PATTERN, $key) !== 1) {
            throw new InvalidRequestException(self::INVALID_KEY_MESSAGE);
        }

        return $key;
    }
}
