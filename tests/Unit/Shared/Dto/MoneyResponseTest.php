<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Dto;

use App\Shared\Dto\MoneyResponse;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyResponseTest extends TestCase
{
    public function testRepresentsAmountInMinorUnitsAsString(): void
    {
        self::assertSame(
            ['amount' => '123456', 'currency' => 'PLN'],
            MoneyResponse::fromMoney(Money::PLN(123456))->toArray(),
        );
    }
}
