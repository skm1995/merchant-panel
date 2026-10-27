<?php

declare(strict_types=1);

namespace App\Shared\Cache\Exception;

use RuntimeException;
use Throwable;

/**
 * The cache server cannot be reached; callers that can work without it catch this.
 */
final class CacheUnavailableException extends RuntimeException
{
    private const string MESSAGE = 'The cache is unavailable.';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, previous: $previous);
    }
}
