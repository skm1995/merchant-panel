<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Enum;

use App\Shared\Http\Enum\HttpMethodEnum;
use PHPUnit\Framework\TestCase;

final class HttpMethodEnumTest extends TestCase
{
    private const string GET_VALUE = 'GET';

    public function testGetCaseHasGetAsItsBackedValue(): void
    {
        self::assertSame(self::GET_VALUE, HttpMethodEnum::GET->value);
    }

}
