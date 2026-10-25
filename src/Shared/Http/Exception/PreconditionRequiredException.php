<?php

declare(strict_types=1);

namespace App\Shared\Http\Exception;

use RuntimeException;

/**
 * Thrown when a conditional request lacks its precondition header; the router answers it with HTTP 428.
 */
final class PreconditionRequiredException extends RuntimeException
{
}
