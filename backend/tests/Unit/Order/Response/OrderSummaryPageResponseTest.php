<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Response;

use App\Order\Enum\OrderStatusEnum;
use App\Order\ReadModel\OrderSummary;
use App\Order\ReadModel\OrderSummaryPage;
use App\Order\Response\OrderSummaryPageResponse;
use DateTimeImmutable;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class OrderSummaryPageResponseTest extends TestCase
{
    public function testDescribesOrdersAndTheNextCursor(): void
    {
        $summary = new OrderSummary(OrderFixtures::orderId(), OrderStatusEnum::PAID, Money::PLN(2500), new DateTimeImmutable('2026-10-25T12:00:00+00:00'));

        $body = OrderSummaryPageResponse::fromPage(new OrderSummaryPage([$summary], OrderFixtures::orderId()))->toArray();

        self::assertSame([
            'data' => [[
                'id' => OrderFixtures::ORDER_ID,
                'status' => 'paid',
                'total' => ['amount' => '2500', 'currency' => 'PLN'],
                'created_at' => '2026-10-25T12:00:00+00:00',
            ]],
            'next_cursor' => OrderFixtures::ORDER_ID,
        ], $body);
    }
}
