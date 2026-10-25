<?php

declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\HeaderNameEnum;
use PHPUnit\Framework\TestCase;

final class HeaderNameEnumTest extends TestCase
{
    private const string CONTENT_TYPE_HEADER = 'Content-Type';

    public function testContentTypeHeaderHasItsExpectedValue(): void
    {
        self::assertSame(self::CONTENT_TYPE_HEADER, HeaderNameEnum::CONTENT_TYPE->value);
    }
}
