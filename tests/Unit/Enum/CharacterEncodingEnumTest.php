<?php

declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\CharacterEncodingEnum;
use PHPUnit\Framework\TestCase;

final class CharacterEncodingEnumTest extends TestCase
{
    private const string UTF8_ENCODING = 'UTF-8';

    public function testUtf8CaseHasItsExpectedValue(): void
    {
        self::assertSame(self::UTF8_ENCODING, CharacterEncodingEnum::UTF8->value);
    }
}
