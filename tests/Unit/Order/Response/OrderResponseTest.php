<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Response;

use App\Order\Response\OrderResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class OrderResponseTest extends TestCase
{
    public function testDescribesFrozenPricesAndReservation(): void
    {
        self::assertSame([
            'id' => OrderFixtures::ORDER_ID,
            'status' => 'pending_payment',
            'items' => [[
                'product_id' => 5,
                'sku' => 'SKU-5',
                'name' => 'Product 5',
                'quantity' => 2,
                'unit_price' => ['amount' => '1250', 'currency' => 'PLN'],
                'line_total' => ['amount' => '2500', 'currency' => 'PLN'],
            ]],
            'total' => ['amount' => '2500', 'currency' => 'PLN'],
            'reservation_expires_at' => OrderFixtures::RESERVATION_EXPIRES_AT,
            'created_at' => OrderFixtures::CREATED_AT,
        ], OrderResponse::fromView(OrderFixtures::view())->toArray());
    }
}
