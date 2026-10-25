<?php

declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\ContentTypeEnum;
use PHPUnit\Framework\TestCase;

final class ContentTypeEnumTest extends TestCase
{
    private const string HTML_CONTENT_TYPE = 'text/html; charset=UTF-8';
    private const string PLAIN_CONTENT_TYPE = 'text/plain; charset=utf-8';

    public function testHtmlContentTypeHasItsExpectedValue(): void
    {
        self::assertSame(self::HTML_CONTENT_TYPE, ContentTypeEnum::HTML->value);
    }

    public function testPlainContentTypeHasItsExpectedValue(): void
    {
        self::assertSame(self::PLAIN_CONTENT_TYPE, ContentTypeEnum::PLAIN->value);
    }
}
