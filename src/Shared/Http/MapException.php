<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\ResponseStatusEnum;
use Attribute;
use Throwable;

/**
 * Declares that a route answers the given exception type with a problem details response.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class MapException
{
    /** @param class-string<Throwable> $exception */
    public function __construct(public string $exception, public ResponseStatusEnum $status)
    {
    }
}
