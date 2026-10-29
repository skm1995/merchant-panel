<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;

/**
 * Where a response goes: PHP's output in production, a recording in tests. Everything that
 * answers a request writes through it, so no other code touches PHP's global response state.
 */
interface ResponseWriterInterface
{
    public function writeStatus(ResponseStatusEnum $status): void;

    public function writeHeader(HeaderNameEnum $name, string $value): void;

    public function writeBody(string $body): void;

    /**
     * The status written so far, or 200 when none was.
     */
    public function writtenStatus(): int;
}
