<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Command;

use App\Cart\Entity\CartId;
use App\Cart\Enum\CartStatusEnum;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Repository\CartRepositoryInterface;
use App\Cart\Service\CartService;
use App\Catalog\Exception\InsufficientStockException;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Catalog\Service\StockReservation;
use App\Order\Command\PlaceOrderCommand;
use App\Order\Command\ExpireOrderReservationCommand;
use App\Order\Command\PlaceOrderHandler;
use App\Order\Entity\Order;
use App\Order\Entity\OrderPlacement;
use App\Order\Exception\IdempotencyKeyReusedException;
use App\Order\Repository\OrderRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;
use DateTimeImmutable;
use Money\Money;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Support\FrozenClock;

final class PlaceOrderHandlerTest extends TestCase
{
    private const string NOW = '2026-10-25 12:00:00';
    private const string OTHER_CART_ID = '7c6b5a49-3827-4165-9054-3a2b1c0d9e8f';
    private const int PRODUCT_ID = 5;
    private const int QUANTITY = 2;
    private const int RESERVATION_SECONDS = 900;

    private ImmediateTransactionRunner $transactions;

    protected function setUp(): void
    {
        $this->transactions = new ImmediateTransactionRunner();
    }

    public function testReservesStockAndPlacesOrderAtCurrentPrices(): void
    {
        $carts = $this->openCart();
        $carts->expects(self::once())->method('updateStatus')->with(CartFixtures::cartId(), CartStatusEnum::CHECKED_OUT);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(self::PRODUCT_ID)]);
        $products->expects(self::once())->method('reserve')->with(self::PRODUCT_ID, self::QUANTITY)->willReturn(true);
        $stored = null;
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findPlacement')->willReturn(null);
        $orders->expects(self::once())->method('add')->willReturnCallback(static function (Order $order) use (&$stored): void {
            $stored = $order;
        });

        $scheduled = [];
        $outbox = $this->createMock(OutboxInterface::class);
        $outbox->expects(self::once())->method('add')->willReturnCallback(
            static function (OutboxMessageInterface $message, DateTimeImmutable $deliverAt) use (&$scheduled): void {
                $scheduled = [$message, $deliverAt];
            },
        );

        $placed = $this->handler($carts, $products, $orders, $outbox)->handle(self::command());

        $expiresAt = new DateTimeImmutable(self::NOW)->modify(sprintf('+%d seconds', self::RESERVATION_SECONDS));
        self::assertInstanceOf(Order::class, $stored);
        self::assertSame($placed->orderId->value, $stored->id->value);
        self::assertSame(OrderFixtures::access()->tokenFor($stored->id), $placed->accessToken);
        self::assertTrue(Money::PLN(2 * 1999)->equals($stored->total()));
        self::assertEquals($expiresAt, $stored->reservationExpiresAt);
        self::assertInstanceOf(ExpireOrderReservationCommand::class, $scheduled[0]);
        self::assertSame($placed->orderId->value, $scheduled[0]->orderId->value);
        self::assertEquals($expiresAt, $scheduled[1]);
        self::assertSame(IsolationLevelEnum::READ_COMMITTED, $this->transactions->lastIsolationLevel);
    }

    public function testRepeatedKeyForSameCartReturnsExistingOrderWithoutReservingAgain(): void
    {
        $carts = $this->checkedOutCart();
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('reserve');
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findPlacement')->with(OrderFixtures::IDEMPOTENCY_KEY)
            ->willReturn(new OrderPlacement(OrderFixtures::orderId(), CartFixtures::cartId()));
        $orders->expects(self::never())->method('add');

        $placed = $this->handler($carts, $products, $orders)->handle(self::command());

        self::assertSame(OrderFixtures::ORDER_ID, $placed->orderId->value);
        self::assertSame(OrderFixtures::accessToken(), $placed->accessToken, 'A repeated checkout gets the same token again.');
    }

    public function testKeyUsedForAnotherCartIsRejected(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findPlacement')->willReturn(
            new OrderPlacement(OrderFixtures::orderId(), CartId::fromString(self::OTHER_CART_ID)),
        );

        $carts = $this->openCart();
        $carts->expects(self::never())->method('updateStatus');

        $this->expectException(IdempotencyKeyReusedException::class);
        $this->handler($carts, $this->createStub(ProductRepositoryInterface::class), $orders)->handle(self::command());
    }

    public function testCheckedOutCartWithNewKeyIsRejected(): void
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findPlacement')->willReturn(null);

        $this->expectException(CartNotOpenException::class);
        $this->handler($this->checkedOutCart(), $this->createStub(ProductRepositoryInterface::class), $orders)->handle(self::command());
    }

    public function testInsufficientStockPlacesNoOrder(): void
    {
        $carts = $this->openCart();
        $carts->expects(self::never())->method('updateStatus');
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lockForUpdate')->willReturn([ProductFactory::create(self::PRODUCT_ID, 1)]);
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findPlacement')->willReturn(null);
        $orders->expects(self::never())->method('add');

        $this->expectException(InsufficientStockException::class);
        $this->handler($carts, $products, $orders)->handle(self::command());
    }

    private static function command(): PlaceOrderCommand
    {
        return new PlaceOrderCommand(CartFixtures::cartId(), OrderFixtures::IDEMPOTENCY_KEY);
    }

    private function openCart(): CartRepositoryInterface&MockObject
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->method('findStatusForUpdate')->willReturn(CartStatusEnum::OPEN);
        $carts->method('find')->willReturn(CartFixtures::cart([CartFixtures::line(self::PRODUCT_ID, self::QUANTITY, 1500)]));

        return $carts;
    }

    private function checkedOutCart(): CartRepositoryInterface
    {
        $carts = $this->createStub(CartRepositoryInterface::class);
        $carts->method('findStatusForUpdate')->willReturn(CartStatusEnum::CHECKED_OUT);
        $carts->method('find')->willReturn(CartFixtures::cart(
            [CartFixtures::line(self::PRODUCT_ID, self::QUANTITY, 1500)],
            CartStatusEnum::CHECKED_OUT,
        ));

        return $carts;
    }

    private function handler(
        CartRepositoryInterface $carts,
        ProductRepositoryInterface $products,
        OrderRepositoryInterface $orders,
        ?OutboxInterface $outbox = null,
    ): PlaceOrderHandler {
        return new PlaceOrderHandler(
            $orders,
            new CartService($carts, new ProductCatalog($products), $this->transactions),
            new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock()),
            $outbox ?? $this->createStub(OutboxInterface::class),
            $this->transactions,
            new FrozenClock(self::NOW),
            OrderFixtures::access(),
            self::RESERVATION_SECONDS,
        );
    }
}
