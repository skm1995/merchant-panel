<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Enum;

use App\Shared\Http\Enum\ResponseStatusEnum;
use PHPUnit\Framework\TestCase;

final class ResponseStatusEnumTest extends TestCase
{
    private const int NOT_FOUND_STATUS = 404;
    private const string NOT_FOUND_MESSAGE = 'Not Found';
    private const int SEE_OTHER_STATUS = 303;
    private const string SEE_OTHER_MESSAGE = 'See Other';

    public function testNotFoundCaseProvidesCodeAndMessage(): void
    {
        self::assertSame(self::NOT_FOUND_STATUS, ResponseStatusEnum::NOT_FOUND->value);
        self::assertSame(self::NOT_FOUND_MESSAGE, ResponseStatusEnum::NOT_FOUND->message());
    }

    public function testSeeOtherCaseProvidesCodeAndMessage(): void
    {
        self::assertSame(self::SEE_OTHER_STATUS, ResponseStatusEnum::SEE_OTHER->value);
        self::assertSame(self::SEE_OTHER_MESSAGE, ResponseStatusEnum::SEE_OTHER->message());
    }
}
