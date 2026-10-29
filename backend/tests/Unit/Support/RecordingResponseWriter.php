<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\ResponseWriterInterface;
use PHPUnit\Framework\Assert;

/**
 * Keeps the response a test's code wrote, so the test reads its status, headers, and body
 * instead of PHP's global response state.
 */
final class RecordingResponseWriter implements ResponseWriterInterface
{
    public int $status = ResponseStatusEnum::OK->value;
    /** @var array<string, string> header values keyed by name */
    public array $headers = [];
    public string $body = '';

    public function writeStatus(ResponseStatusEnum $status): void
    {
        $this->status = $status->value;
    }

    public function writeHeader(HeaderNameEnum $name, string $value): void
    {
        $this->headers[$name->value] = $value;
    }

    public function writeBody(string $body): void
    {
        $this->body .= $body;
    }

    public function writtenStatus(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $body = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
        Assert::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }
}
