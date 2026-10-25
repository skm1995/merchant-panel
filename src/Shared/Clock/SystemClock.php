<?php

declare(strict_types=1);

namespace App\Shared\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * The real clock. Code depends on ClockInterface so tests can freeze time instead.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
