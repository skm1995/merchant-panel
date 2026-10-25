<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock that stands still until a test moves it forward with sleep().
 */
final class FrozenClock implements ClockInterface
{
    private const string CURRENT_TIME = 'now';
    private const string ADVANCE_FORMAT = '+%d microseconds';
    private const int MICROSECONDS_PER_SECOND = 1_000_000;

    private DateTimeImmutable $now;

    public function __construct(string $now = self::CURRENT_TIME)
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function sleep(int|float $seconds): void
    {
        $this->now = $this->now->modify(sprintf(self::ADVANCE_FORMAT, (int) round($seconds * self::MICROSECONDS_PER_SECOND)));
    }
}
