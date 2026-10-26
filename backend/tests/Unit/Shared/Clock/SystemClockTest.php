<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Clock;

use App\Shared\Clock\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testReturnsTheCurrentSystemTime(): void
    {
        $before = new DateTimeImmutable();

        $now = new SystemClock()->now();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual(new DateTimeImmutable(), $now);
    }
}
