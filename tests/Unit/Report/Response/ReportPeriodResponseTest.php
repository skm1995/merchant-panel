<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Response;

use App\Report\Response\ReportPeriodResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportPeriodResponseTest extends TestCase
{
    public function testDescribesThePeriodAndCurrency(): void
    {
        self::assertSame(
            ['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, 'currency' => ReportFixtures::CURRENCY],
            ReportPeriodResponse::fromPeriod(ReportFixtures::period())->toArray(),
        );
    }
}
