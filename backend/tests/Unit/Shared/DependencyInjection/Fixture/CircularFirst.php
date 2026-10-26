<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture;

final class CircularFirst
{
    public function __construct(public readonly CircularSecond $second)
    {
    }
}
