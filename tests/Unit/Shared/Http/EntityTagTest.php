<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\EntityTag;
use App\Shared\Http\Exception\InvalidRequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntityTagTest extends TestCase
{
    public function testFormatsVersionAsStrongQuotedTag(): void
    {
        self::assertSame('"7"', EntityTag::fromVersion(7)->value);
    }

    public function testParsesIfMatchVersion(): void
    {
        self::assertSame(7, EntityTag::fromHeader('"7"')->version);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTags(): iterable
    {
        yield 'unquoted' => ['7'];
        yield 'weak' => ['W/"7"'];
        yield 'wildcard' => ['*'];
        yield 'not a version' => ['"abc"'];
        yield 'zero' => ['"0"'];
    }

    #[DataProvider('invalidTags')]
    public function testRejectsAnythingButAStrongVersionTag(string $header): void
    {
        $this->expectException(InvalidRequestException::class);

        EntityTag::fromHeader($header);
    }
}
