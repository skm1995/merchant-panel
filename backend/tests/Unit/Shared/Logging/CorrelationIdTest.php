<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Logging;

use App\Shared\Logging\CorrelationId;
use PHPUnit\Framework\TestCase;

final class CorrelationIdTest extends TestCase
{
    private const string ID = 'f6b0d3c1-5a1e-4c3a-9e1f-0c7b2d4e6a8b';

    public function testHoldsTheIdOfTheCurrentRequestOrMessage(): void
    {
        $correlation = new CorrelationId();

        self::assertNull($correlation->get());

        $correlation->set(self::ID);

        self::assertSame(self::ID, $correlation->get());
    }
}
