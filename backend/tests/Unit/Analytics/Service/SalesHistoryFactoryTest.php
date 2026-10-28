<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics\Service;

use App\Analytics\Service\SalesHistoryFactory;
use App\Analytics\ValueObject\HistoryLine;
use App\Analytics\ValueObject\HistoryOrder;
use App\Analytics\ValueObject\HistoryProduct;
use App\Analytics\ValueObject\SalesDay;
use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class SalesHistoryFactoryTest extends TestCase
{
    private const int PRODUCTS = 50;
    private const int ORDERS = 400;
    private const int FIRST_NUMBER = 1201;
    private const string DAY = '2025-11-28';
    private const string UUID_V4_PATTERN = '/^[0-9a-f]{12}4[0-9a-f]{3}[89ab][0-9a-f]{15}$/';

    public function testCreatesUniquelyNamedProductsWithPricesInPln(): void
    {
        $products = new SalesHistoryFactory()->products(self::PRODUCTS);

        self::assertCount(self::PRODUCTS, $products);
        self::assertCount(self::PRODUCTS, array_unique(array_map(static fn (HistoryProduct $product): string => $product->sku, $products)));
        foreach ($products as $product) {
            self::assertStringStartsWith(SalesHistoryFactory::SKU_PREFIX, $product->sku);
            self::assertSame('PLN', $product->price->getCurrency()->getCode());
            self::assertTrue($product->price->isPositive());
            self::assertGreaterThanOrEqual(0, $product->stock);
        }
    }

    public function testNumbersTheDaysOrdersAndPlacesThemOnThatDay(): void
    {
        $orders = $this->orders();

        self::assertCount(self::ORDERS, $orders);
        self::assertSame(SalesHistoryFactory::KEY_PREFIX . self::FIRST_NUMBER, $orders[0]->idempotencyKey);
        self::assertSame(SalesHistoryFactory::KEY_PREFIX . (self::FIRST_NUMBER + self::ORDERS - 1), $orders[self::ORDERS - 1]->idempotencyKey);
        foreach ($orders as $order) {
            self::assertSame(self::DAY, $order->placedAt->format('Y-m-d'));
            self::assertMatchesRegularExpression(self::UUID_V4_PATTERN, bin2hex($order->publicId));
            self::assertMatchesRegularExpression(self::UUID_V4_PATTERN, bin2hex($order->cartId));
        }
    }

    public function testEachOrderListsOneToFourDifferentProductsAndTotalsItsLines(): void
    {
        $products = $this->products();

        foreach ($this->orders() as $order) {
            $ids = array_map(static fn (HistoryLine $line): int => $line->productId, $order->lines);
            self::assertGreaterThanOrEqual(1, count($ids));
            self::assertLessThanOrEqual(4, count($ids));
            self::assertSame($ids, array_values(array_unique($ids)), 'An order lists each product once.');

            foreach ($order->lines as $line) {
                self::assertTrue($products[$line->productId]->price->equals($line->unitPrice));
                self::assertContains($line->quantity, [1, 2, 3]);
            }

            self::assertTrue(Money::sum(...array_map(static fn (HistoryLine $line): Money => $line->unitPrice->multiply($line->quantity), $order->lines))->equals($order->total()));
        }
    }

    public function testMostOrdersArePaidAndSomeAreCancelledOrExpired(): void
    {
        $statuses = array_count_values(array_map(static fn (HistoryOrder $order): string => $order->status->value, $this->orders()));

        self::assertGreaterThan(0.85 * self::ORDERS, $statuses[OrderStatusEnum::PAID->value]);
        self::assertArrayHasKey(OrderStatusEnum::CANCELLED->value, $statuses);
        self::assertArrayHasKey(OrderStatusEnum::EXPIRED->value, $statuses);
        self::assertArrayNotHasKey(OrderStatusEnum::PENDING_PAYMENT->value, $statuses, 'History holds no open reservations.');
    }

    public function testAFewProductsSellFarMoreThanTheRest(): void
    {
        $units = array_fill_keys(array_keys($this->products()), 0);
        foreach ($this->orders() as $order) {
            foreach ($order->lines as $line) {
                $units[$line->productId] += $line->quantity;
            }
        }
        rsort($units);

        self::assertGreaterThan(3 * $units[intdiv(self::PRODUCTS, 2)], $units[0]);
    }

    public function testCreatesTheSameOrdersForTheSameDay(): void
    {
        self::assertEquals($this->orders(), $this->orders());
    }

    /** @return array<int, HistoryProduct> */
    private function products(): array
    {
        $products = new SalesHistoryFactory()->products(self::PRODUCTS);

        return array_combine(range(101, 100 + self::PRODUCTS), $products);
    }

    /** @return list<HistoryOrder> */
    private function orders(): array
    {
        return new SalesHistoryFactory()->ordersForDay(
            new SalesDay(new DateTimeImmutable(self::DAY), self::ORDERS),
            self::FIRST_NUMBER,
            $this->products(),
        );
    }
}
