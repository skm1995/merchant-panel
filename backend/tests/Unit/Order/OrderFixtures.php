<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use App\Order\Entity\OrderId;
use App\Order\Entity\OrderLine;
use App\Order\Enum\OrderStatusEnum;
use App\Order\ReadModel\OrderLineView;
use App\Order\ReadModel\OrderView;
use DateTimeImmutable;
use Money\Money;

final class OrderFixtures
{
    public const string ORDER_ID = '9b1d2c3e-4f5a-4b6c-8d7e-9f0a1b2c3d4e';
    public const string IDEMPOTENCY_KEY = 'checkout-key-1';
    public const int VERSION = 1;
    public const string CREATED_AT = '2026-10-25T12:00:00+00:00';
    public const string RESERVATION_EXPIRES_AT = '2026-10-25T12:15:00+00:00';

    public static function orderId(): OrderId
    {
        return OrderId::fromString(self::ORDER_ID);
    }

    public static function line(int $productId, int $quantity, int $unitPriceInGrosze): OrderLine
    {
        return new OrderLine($productId, 'SKU-' . $productId, 'Product ' . $productId, $quantity, Money::PLN($unitPriceInGrosze));
    }

    public static function view(): OrderView
    {
        return new OrderView(
            self::orderId(),
            OrderStatusEnum::PENDING_PAYMENT,
            [new OrderLineView(5, 'SKU-5', 'Product 5', 2, Money::PLN(1250))],
            Money::PLN(2500),
            new DateTimeImmutable(self::RESERVATION_EXPIRES_AT),
            new DateTimeImmutable(self::CREATED_AT),
            self::VERSION,
        );
    }
}
