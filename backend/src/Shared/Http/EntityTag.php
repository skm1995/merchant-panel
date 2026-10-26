<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Exception\InvalidRequestException;

/**
 * Strong entity tag carrying a resource version, used for optimistic locking with If-Match.
 */
final readonly class EntityTag
{
    private const string FORMAT = '"%d"';
    private const string PATTERN = '/^"([1-9]\d*)"$/';
    private const string INVALID_MESSAGE = 'The If-Match header must contain the strong ETag of the current resource.';

    private function __construct(public int $version, public string $value)
    {
    }

    public static function fromVersion(int $version): self
    {
        return new self($version, sprintf(self::FORMAT, $version));
    }

    public static function fromHeader(string $header): self
    {
        if (preg_match(self::PATTERN, $header, $matches) !== 1) {
            throw new InvalidRequestException(self::INVALID_MESSAGE);
        }

        return self::fromVersion((int) $matches[1]);
    }
}
