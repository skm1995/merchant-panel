<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

final class CircularSecond
{
    public function __construct(public readonly CircularFirst $first)
    {
    }
}
