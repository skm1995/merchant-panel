<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;

/**
 * Sends the response through PHP-FPM: the only class that calls http_response_code(),
 * header(), and echo.
 */
final class PhpResponseWriter implements ResponseWriterInterface
{
    private const string HEADER_FORMAT = '%s: %s';

    public function writeStatus(ResponseStatusEnum $status): void
    {
        http_response_code($status->value);
    }

    public function writeHeader(HeaderNameEnum $name, string $value): void
    {
        header(sprintf(self::HEADER_FORMAT, $name->value, $value));
    }

    public function writeBody(string $body): void
    {
        echo $body;
    }

    public function writtenStatus(): int
    {
        $status = http_response_code();

        return is_int($status) ? $status : ResponseStatusEnum::OK->value;
    }
}
