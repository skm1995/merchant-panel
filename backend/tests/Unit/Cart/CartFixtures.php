<?php

declare(strict_types=1);

namespace Tests\Unit\Cart;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Entity\CartLine;
use App\Cart\Enum\CartStatusEnum;
use Money\Money;

final class CartFixtures
{
    public const string CART_ID = '3f2b1c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
    private const string SKU_FORMAT = 'SKU-%d';
    private const string NAME_FORMAT = 'Product %d';

    public static function cartId(): CartId
    {
        return CartId::fromString(self::CART_ID);
    }

    public static function line(int $productId, int $quantity, int $unitPriceInGrosze): CartLine
    {
        return new CartLine(
            $productId,
            sprintf(self::SKU_FORMAT, $productId),
            sprintf(self::NAME_FORMAT, $productId),
            $quantity,
            Money::PLN($unitPriceInGrosze),
        );
    }

    /** @param list<CartLine> $lines */
    public static function cart(array $lines = [], CartStatusEnum $status = CartStatusEnum::OPEN): Cart
    {
        return new Cart(self::cartId(), $status, $lines);
    }
}
