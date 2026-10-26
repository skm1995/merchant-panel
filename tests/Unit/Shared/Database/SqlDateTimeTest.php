<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Shared\Database\SqlDateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SqlDateTimeTest extends TestCase
{
    private const string MOMENT = '2026-10-25 12:34:56.789';
    private const string STORED = '2026-10-25 12:34:56';

    public function testFormatsToTheSecondAsMysqlDatetime(): void
    {
        self::assertSame(self::STORED, SqlDateTime::format(new DateTimeImmutable(self::MOMENT)));
    }
}
