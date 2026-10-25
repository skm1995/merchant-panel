<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Command;

use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\StockReservation;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Service\OrderSettlement;
use App\Payment\Command\HandlePaymentWebhookCommand;
use App\Payment\Command\HandlePaymentWebhookHandler;
use App\Payment\Entity\PaymentState;
use App\Payment\Enum\PaymentOutcomeEnum;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\Enum\WebhookResultEnum;
use App\Payment\Exception\PaymentAmountMismatchException;
use App\Payment\Exception\PaymentNotFoundException;
use App\Payment\Gateway\PaymentGatewayFactoryInterface;
use App\Payment\Gateway\PaymentGatewayInterface;
use App\Payment\Gateway\PaymentWebhookEvent;
use App\Payment\Repository\PaymentRepositoryInterface;
use App\Payment\Repository\WebhookInboxRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use Money\Money;
use App\Shared\Messaging\Outbox\OutboxInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Payment\PaymentFixtures;
use Tests\Unit\Support\ImmediateTransactionRunner;
use Tests\Unit\Support\FrozenClock;

final class HandlePaymentWebhookHandlerTest extends TestCase
{
    private const string EVENT_ID = 'evt_1';
    private const string PAYLOAD = '{}';

    private ImmediateTransactionRunner $transactions;

    protected function setUp(): void
    {
        $this->transactions = new ImmediateTransactionRunner();
    }

    public function testSuccessfulPaymentPaysTheOrderAndCommitsStock(): void
    {
        $payments = $this->pendingPayment();
        $payments->expects(self::once())->method('updateStatus')->with(PaymentFixtures::paymentId(), PaymentStatusEnum::SUCCEEDED);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())->method('commit')->willReturn(true);

        $result = $this->handler($payments, $this->event(PaymentOutcomeEnum::SUCCEEDED), $products)->handle(self::command());

        self::assertSame(WebhookResultEnum::PROCESSED, $result);
        self::assertSame(IsolationLevelEnum::READ_COMMITTED, $this->transactions->lastIsolationLevel);
    }

    public function testPaymentForAnOrderThatCannotBePaidRequiresARefund(): void
    {
        $payments = $this->pendingPayment();
        $payments->expects(self::once())->method('updateStatus')->with(PaymentFixtures::paymentId(), PaymentStatusEnum::REFUND_REQUIRED);

        $this->handler($payments, $this->event(PaymentOutcomeEnum::SUCCEEDED), orderStatus: OrderStatusEnum::CANCELLED)
            ->handle(self::command());
    }

    public function testFailedPaymentLeavesTheOrderAwaitingPayment(): void
    {
        $payments = $this->pendingPayment();
        $payments->expects(self::once())->method('updateStatus')->with(PaymentFixtures::paymentId(), PaymentStatusEnum::FAILED);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('commit');

        $this->handler($payments, $this->event(PaymentOutcomeEnum::FAILED), $products)->handle(self::command());
    }

    public function testDuplicateEventChangesNothing(): void
    {
        $payments = $this->createMock(PaymentRepositoryInterface::class);
        $payments->expects(self::never())->method('lockByProviderPaymentId');

        $result = $this->handler($payments, $this->event(PaymentOutcomeEnum::SUCCEEDED), firstDelivery: false)
            ->handle(self::command());

        self::assertSame(WebhookResultEnum::DUPLICATE, $result);
    }

    public function testSettledPaymentIgnoresLaterEvents(): void
    {
        $payments = $this->createMock(PaymentRepositoryInterface::class);
        $payments->method('lockByProviderPaymentId')->willReturn(self::paymentState(PaymentStatusEnum::SUCCEEDED, 2500));
        $payments->expects(self::never())->method('updateStatus');

        $result = $this->handler($payments, $this->event(PaymentOutcomeEnum::FAILED))->handle(self::command());

        self::assertSame(WebhookResultEnum::DUPLICATE, $result);
    }

    public function testRejectsAmountDifferentFromThePayment(): void
    {
        $payments = $this->createStub(PaymentRepositoryInterface::class);
        $payments->method('lockByProviderPaymentId')->willReturn(self::paymentState(PaymentStatusEnum::PENDING, 9999));

        $this->expectException(PaymentAmountMismatchException::class);
        $this->handler($payments, $this->event(PaymentOutcomeEnum::SUCCEEDED))->handle(self::command());
    }

    public function testReportsUnknownPayment(): void
    {
        $payments = $this->createStub(PaymentRepositoryInterface::class);
        $payments->method('lockByProviderPaymentId')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);
        $this->handler($payments, $this->event(PaymentOutcomeEnum::SUCCEEDED))->handle(self::command());
    }

    private static function command(): HandlePaymentWebhookCommand
    {
        return new HandlePaymentWebhookCommand(PaymentProviderEnum::FAKEPAY, self::PAYLOAD, []);
    }

    private static function paymentState(PaymentStatusEnum $status, int $amountInGrosze): PaymentState
    {
        return new PaymentState(PaymentFixtures::paymentId(), OrderFixtures::orderId(), $status, Money::PLN($amountInGrosze));
    }

    private function event(PaymentOutcomeEnum $outcome): PaymentWebhookEvent
    {
        return new PaymentWebhookEvent(self::EVENT_ID, PaymentFixtures::PROVIDER_PAYMENT_ID, $outcome, Money::PLN(2500));
    }

    private function pendingPayment(): PaymentRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $payments = $this->createMock(PaymentRepositoryInterface::class);
        $payments->method('lockByProviderPaymentId')->willReturn(self::paymentState(PaymentStatusEnum::PENDING, 2500));

        return $payments;
    }

    private function handler(
        PaymentRepositoryInterface $payments,
        PaymentWebhookEvent $event,
        ?ProductRepositoryInterface $products = null,
        OrderStatusEnum $orderStatus = OrderStatusEnum::PENDING_PAYMENT,
        bool $firstDelivery = true,
    ): HandlePaymentWebhookHandler {
        $gateway = $this->createStub(PaymentGatewayInterface::class);
        $gateway->method('parseWebhook')->willReturn($event);
        $gateways = $this->createStub(PaymentGatewayFactoryInterface::class);
        $gateways->method('create')->willReturn($gateway);
        $inbox = $this->createStub(WebhookInboxRepositoryInterface::class);
        $inbox->method('record')->willReturn($firstDelivery);
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('findState')->willReturn(new OrderState(OrderFixtures::orderId(), $orderStatus, 1, [5 => 2]));
        $orders->method('transition')->willReturn(true);
        $products ??= $this->createStub(ProductRepositoryInterface::class);

        return new HandlePaymentWebhookHandler(
            $gateways,
            $inbox,
            $payments,
            new OrderSettlement($orders, new StockReservation($products, $this->createStub(OutboxInterface::class), new FrozenClock())),
            $this->transactions,
        );
    }
}
