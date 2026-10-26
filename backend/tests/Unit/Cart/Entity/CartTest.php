<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Entity;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Enum\CartStatusEnum;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\EmptyCartException;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;

final class CartTest extends TestCase
{
    public function testANewCartIsOpenAndEmpty(): void
    {
        $id = CartId::generate();

        $cart = Cart::open($id);

        self::assertSame($id, $cart->id);
        self::assertTrue($cart->isOpen());
        self::assertSame([], $cart->lines);
    }

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

    public function testExposesQuantitiesKeyedByProduct(): void
    {
        $cart = CartFixtures::cart([CartFixtures::line(4, 2, 100), CartFixtures::line(9, 1, 100)]);

        self::assertSame([4 => 2, 9 => 1], $cart->quantities());
    }

    public function testOpenCartWithItemsIsReadyForCheckout(): void
    {
        CartFixtures::cart([CartFixtures::line(4, 1, 100)])->assertReadyForCheckout();

        self::addToAssertionCount(1);
    }

    public function testEmptyCartCannotBeCheckedOut(): void
    {
        $this->expectException(EmptyCartException::class);

        CartFixtures::cart()->assertReadyForCheckout();
    }

    public function testCheckedOutCartCannotBeCheckedOutAgain(): void
    {
        $this->expectException(CartNotOpenException::class);

        CartFixtures::cart([CartFixtures::line(4, 1, 100)], CartStatusEnum::CHECKED_OUT)->assertReadyForCheckout();
    }

    public function testLineTotalMultipliesUnitPrice(): void
    {
        self::assertTrue(Money::PLN(2997)->equals(CartFixtures::line(1, 3, 999)->lineTotal()));
    }
}
