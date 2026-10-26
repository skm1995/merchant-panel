<?php

declare(strict_types=1);

namespace App\Shared\Http\Exception;

use RuntimeException;

/**
 * Thrown while parsing request input that is malformed; the router answers it with HTTP 400.
 */
final class InvalidRequestException extends RuntimeException
{
}
