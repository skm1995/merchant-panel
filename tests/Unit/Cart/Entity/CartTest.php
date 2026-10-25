<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Entity;

use App\Cart\Enum\CartStatusEnum;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;

final class CartTest extends TestCase
{
    public function testTotalSumsLineTotals(): void
    {
        $cart = CartFixtures::cart([CartFixtures::line(1, 2, 1000), CartFixtures::line(2, 3, 250)]);

        self::assertTrue(Money::PLN(2750)->equals($cart->total() ?? Money::PLN(0)));
    }

    public function testEmptyCartHasNoTotal(): void
    {
        self::assertNull(CartFixtures::cart()->total());
    }

    public function testOnlyOpenCartAcceptsChanges(): void
    {
        self::assertTrue(CartFixtures::cart()->isOpen());
        self::assertFalse(CartFixtures::cart([], CartStatusEnum::CHECKED_OUT)->isOpen());
    }

    public function testLineTotalMultipliesUnitPrice(): void
    {
        self::assertTrue(Money::PLN(2997)->equals(CartFixtures::line(1, 3, 999)->lineTotal()));
    }
}
