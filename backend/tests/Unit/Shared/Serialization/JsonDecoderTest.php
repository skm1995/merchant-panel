<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Serialization;

use App\Shared\Serialization\JsonDecoder;
use PHPUnit\Framework\TestCase;

final class JsonDecoderTest extends TestCase
{
    public function testDecodesObjectsAndArrays(): void
    {
        self::assertSame(['a' => 1], JsonDecoder::toArray('{"a":1}'));
        self::assertSame([1, 2], JsonDecoder::toArray('[1,2]'));
    }

    public function testReturnsNullForInvalidJsonOrScalars(): void
    {
        self::assertNull(JsonDecoder::toArray('{broken'));
        self::assertNull(JsonDecoder::toArray('"text"'));
        self::assertNull(JsonDecoder::toArray('null'));
    }
}
