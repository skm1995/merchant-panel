<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Service;

use App\Report\Exception\InvalidReportPeriodException;
use App\Report\Service\ReportPeriod;
use DateTimeImmutable;
use Money\Currency;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportPeriodTest extends TestCase
{
    public function testCoversWholeDaysWithAnExclusiveUpperBound(): void
    {
        $period = ReportFixtures::period();

        self::assertSame('2026-10-01 00:00:00', $period->startsAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-08 00:00:00', $period->endsBefore()->format('Y-m-d H:i:s'));
    }

    public function testRejectsReversedPeriods(): void
    {
        $this->expectException(InvalidReportPeriodException::class);

        new ReportPeriod(new DateTimeImmutable(ReportFixtures::TO), new DateTimeImmutable(ReportFixtures::FROM), new Currency(ReportFixtures::CURRENCY));
    }

    public function testRejectsPeriodsLongerThanAYear(): void
    {
        $this->expectException(InvalidReportPeriodException::class);

        new ReportPeriod(
            new DateTimeImmutable('2025-01-01'),
            new DateTimeImmutable('2025-01-01')->modify(sprintf('+%d days', ReportPeriod::MAXIMUM_DAYS)),
            new Currency(ReportFixtures::CURRENCY),
        );
    }
}
