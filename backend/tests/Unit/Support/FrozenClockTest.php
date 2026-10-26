<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FrozenClockTest extends TestCase
{
    private const string NOW = '2026-10-25 12:00:00';
    private const string LATER = '2026-10-25 12:01:30.250000';

    public function testStaysAtTheGivenTime(): void
    {
        $clock = new FrozenClock(self::NOW);

        self::assertEquals(new DateTimeImmutable(self::NOW), $clock->now());
        self::assertEquals($clock->now(), $clock->now());
    }

    public function testAdvancesOnlyWhenTheTestSleeps(): void
    {
        $clock = new FrozenClock(self::NOW);

        $clock->sleep(90);
        $clock->sleep(0.25);

        self::assertEquals(new DateTimeImmutable(self::LATER), $clock->now());
    }

    public function testStartsAtTheCurrentTimeByDefault(): void
    {
        $before = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, new FrozenClock()->now());
    }
}
