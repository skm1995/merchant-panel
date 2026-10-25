<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Enum;

use App\Shared\Http\Enum\ServerParameterEnum;
use PHPUnit\Framework\TestCase;

final class ServerParameterEnumTest extends TestCase
{
    private const string REQUEST_METHOD_PARAMETER = 'REQUEST_METHOD';
    private const string REQUEST_URI_PARAMETER = 'REQUEST_URI';

    public function testRequestMethodCaseHasItsExpectedValue(): void
    {
        self::assertSame(self::REQUEST_METHOD_PARAMETER, ServerParameterEnum::REQUEST_METHOD->value);
    }

    public function testRequestUriCaseHasItsExpectedValue(): void
    {
        self::assertSame(self::REQUEST_URI_PARAMETER, ServerParameterEnum::REQUEST_URI->value);
    }
}
