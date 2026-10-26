<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Response;

use App\Cart\Response\CartResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;

final class CartResponseTest extends TestCase
{
    public function testDescribesLinesAndTotalsInMinorUnits(): void
    {
        $body = CartResponse::fromCart(CartFixtures::cart([CartFixtures::line(5, 2, 1250)]))->toArray();

        self::assertSame([
            'id' => CartFixtures::CART_ID,
            'status' => 'open',
            'items' => [[
                'product_id' => 5,
                'sku' => 'SKU-5',
                'name' => 'Product 5',
                'quantity' => 2,
                'unit_price' => ['amount' => '1250', 'currency' => 'PLN'],
                'line_total' => ['amount' => '2500', 'currency' => 'PLN'],
            ]],
            'total' => ['amount' => '2500', 'currency' => 'PLN'],
        ], $body);
    }

    public function testEmptyCartHasNullTotal(): void
    {
        self::assertNull(CartResponse::fromCart(CartFixtures::cart())->toArray()['total']);
    }
}
