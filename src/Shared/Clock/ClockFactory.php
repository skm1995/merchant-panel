<?php

declare(strict_types=1);

namespace App\Shared\Clock;

use App\Shared\DependencyInjection\ServiceFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

final class ClockFactory
{
    #[ServiceFactory]
    public static function create(): ClockInterface
    {
        return new NativeClock();
    }
}
