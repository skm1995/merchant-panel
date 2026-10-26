<?php

declare(strict_types=1);

namespace App\Payment\Command;

use App\Order\Enum\OrderStatusEnum;
use App\Order\Query\GetOrderHandler;
use App\Order\Query\GetOrderQuery;
use App\Payment\Entity\Payment;
use App\Payment\Entity\PaymentId;
use App\Payment\Entity\PaymentStart;
use App\Payment\Exception\OrderNotPayableException;
use App\Payment\Exception\PaymentIdempotencyKeyReusedException;
use App\Payment\Gateway\PaymentGatewayFactoryInterface;
use App\Payment\Repository\PaymentRepositoryInterface;

/**
 * Starts a payment for the full order total at the chosen provider.
 *
 * The provider call is a side effect outside the database, so it is not wrapped in a
 * transaction: the payment is stored afterwards, and a concurrent request with the same
 * Idempotency-Key that loses the unique-index race returns the stored payment instead.
 * An order that changes after this check is handled when the provider reports the result.
 */
final readonly class StartPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private GetOrderHandler $orders,
        private PaymentGatewayFactoryInterface $gateways,
    ) {
    }

    public function handle(StartPaymentCommand $command): PaymentId
    {
        $existing = $this->payments->findStart($command->idempotencyKey);

        if ($existing !== null) {
            return self::replay($existing, $command);
        }

        $order = $this->orders->handle(new GetOrderQuery($command->orderId));

        if (!$order->status->canTransitionTo(OrderStatusEnum::PAID)) {
            throw new OrderNotPayableException();
        }

        $paymentId = PaymentId::generate();
        $gatewayPayment = $this->gateways->create($command->provider)->createPayment($paymentId, $order->total);

        try {
            $this->payments->add(Payment::start(
                $paymentId,
                $command->orderId,
                $command->provider,
                $gatewayPayment,
                $order->total,
                $command->idempotencyKey,
            ));
        } catch (PaymentIdempotencyKeyReusedException $exception) {
            return self::replay($this->payments->findStart($command->idempotencyKey) ?? throw $exception, $command);
        }

        return $paymentId;
    }

    private static function replay(PaymentStart $existing, StartPaymentCommand $command): PaymentId
    {
        return $existing->orderId->equals($command->orderId)
            ? $existing->paymentId
            : throw new PaymentIdempotencyKeyReusedException();
    }
}
