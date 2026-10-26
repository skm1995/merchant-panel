<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Query;

use App\Report\Query\ReportDate;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportDateTest extends TestCase
{
    private const string DATE = '2026-02-28';

    public function testParsesARealDateAtMidnightAndFormatsItBack(): void
    {
        $date = ReportDate::parse(self::DATE);

        self::assertEquals(new DateTimeImmutable(self::DATE . ' 00:00:00'), $date);
        self::assertNotNull($date);
        self::assertSame(self::DATE, ReportDate::format($date));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDates(): iterable
    {
        yield 'impossible day' => ['2026-02-30'];
        yield 'wrong format' => ['28.02.2026'];
        yield 'trailing text' => ['2026-02-28x'];
    }

    #[DataProvider('invalidDates')]
    public function testRejectsAnythingButARealDate(string $value): void
    {
        self::assertNull(ReportDate::parse($value));
    }
}
