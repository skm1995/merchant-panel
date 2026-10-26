<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Command;

use App\Order\Enum\OrderStatusEnum;
use App\Order\Query\GetOrderHandler;
use App\Order\ReadModel\OrderView;
use App\Order\Repository\OrderReadRepositoryInterface;
use App\Payment\Command\StartPaymentCommand;
use App\Payment\Command\StartPaymentHandler;
use App\Payment\Entity\Payment;
use App\Payment\Entity\PaymentStart;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\Exception\OrderNotPayableException;
use App\Payment\Exception\PaymentIdempotencyKeyReusedException;
use App\Payment\Gateway\GatewayPayment;
use App\Payment\Gateway\PaymentGatewayFactoryInterface;
use App\Payment\Gateway\PaymentGatewayInterface;
use App\Payment\Repository\PaymentRepositoryInterface;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Payment\PaymentFixtures;

final class StartPaymentHandlerTest extends TestCase
{
    private const string OTHER_ORDER_ID = '5a4b3c2d-1e0f-4a9b-8c7d-6e5f4a3b2c1d';

    public function testCreatesPaymentForTheOrderTotalWithTheChosenProvider(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects(self::once())->method('createPayment')
            ->with(self::anything(), self::callback(static fn (Money $amount): bool => $amount->equals(Money::PLN(2500))))
            ->willReturn(new GatewayPayment(PaymentFixtures::PROVIDER_PAYMENT_ID, PaymentFixtures::CHECKOUT_URL));
        $stored = null;
        $payments = $this->createMock(PaymentRepositoryInterface::class);
        $payments->method('findStart')->willReturn(null);
        $payments->expects(self::once())->method('add')->willReturnCallback(static function (Payment $payment) use (&$stored): void {
            $stored = $payment;
        });

        $paymentId = $this->handler($payments, $gateway)->handle(self::command());

        self::assertInstanceOf(Payment::class, $stored);
        self::assertSame($paymentId->value, $stored->id->value);
        self::assertSame(PaymentStatusEnum::PENDING, $stored->status);
        self::assertSame(PaymentFixtures::PROVIDER_PAYMENT_ID, $stored->providerPaymentId);
    }

    public function testRepeatedKeyReturnsTheExistingPaymentWithoutCallingTheGateway(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects(self::never())->method('createPayment');
        $payments = $this->createStub(PaymentRepositoryInterface::class);
        $payments->method('findStart')->willReturn(new PaymentStart(PaymentFixtures::paymentId(), OrderFixtures::orderId()));

        $paymentId = $this->handler($payments, $gateway)->handle(self::command());

        self::assertSame(PaymentFixtures::PAYMENT_ID, $paymentId->value);
    }

    public function testKeyUsedForAnotherOrderIsRejected(): void
    {
        $payments = $this->createStub(PaymentRepositoryInterface::class);
        $payments->method('findStart')->willReturn(new PaymentStart(
            PaymentFixtures::paymentId(),
            \App\Order\Entity\OrderId::fromString(self::OTHER_ORDER_ID),
        ));

        $this->expectException(PaymentIdempotencyKeyReusedException::class);
        $this->handler($payments, $this->createStub(PaymentGatewayInterface::class))->handle(self::command());
    }

    public function testOnlyOrdersAwaitingPaymentCanBePaid(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects(self::never())->method('createPayment');
        $payments = $this->createStub(PaymentRepositoryInterface::class);
        $payments->method('findStart')->willReturn(null);

        $this->expectException(OrderNotPayableException::class);
        $this->handler($payments, $gateway, OrderStatusEnum::CANCELLED)->handle(self::command());
    }

    private static function command(): StartPaymentCommand
    {
        return new StartPaymentCommand(OrderFixtures::orderId(), PaymentProviderEnum::FAKEPAY, PaymentFixtures::IDEMPOTENCY_KEY);
    }

    private function handler(
        PaymentRepositoryInterface $payments,
        PaymentGatewayInterface $gateway,
        OrderStatusEnum $orderStatus = OrderStatusEnum::PENDING_PAYMENT,
    ): StartPaymentHandler {
        $view = OrderFixtures::view();
        $orders = $this->createStub(OrderReadRepositoryInterface::class);
        $orders->method('find')->willReturn(new OrderView(
            $view->id,
            $orderStatus,
            $view->lines,
            $view->total,
            $view->reservationExpiresAt,
            $view->createdAt,
            $view->version,
        ));
        $gateways = $this->createStub(PaymentGatewayFactoryInterface::class);
        $gateways->method('create')->willReturn($gateway);

        return new StartPaymentHandler($payments, new GetOrderHandler($orders), $gateways);
    }
}
