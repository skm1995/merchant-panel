<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

use Stringable;

final class PrintableLabel implements Stringable
{
    private const string LABEL = 'label';

    public function __toString(): string
    {
        return self::LABEL;
    }
}
