<?php

declare(strict_types=1);

namespace App\Payment\Command;

use App\Order\Service\OrderSettlement;
use App\Payment\Enum\PaymentOutcomeEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\Enum\WebhookResultEnum;
use App\Payment\Exception\PaymentAmountMismatchException;
use App\Payment\Exception\PaymentNotFoundException;
use App\Payment\Gateway\PaymentGatewayFactoryInterface;
use App\Payment\Gateway\PaymentWebhookEvent;
use App\Payment\Repository\PaymentRepositoryInterface;
use App\Payment\Repository\WebhookInboxRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;

/**
 * Applies a provider webhook exactly once.
 *
 * The signature is verified first, outside the transaction. Inside it, the event id is
 * recorded in the inbox (a repeated delivery stops there) and the payment row is locked,
 * so concurrent deliveries for the same payment are serialized and a settled payment
 * ignores later events.
 */
final readonly class HandlePaymentWebhookHandler
{
    public function __construct(
        private PaymentGatewayFactoryInterface $gateways,
        private WebhookInboxRepositoryInterface $inbox,
        private PaymentRepositoryInterface $payments,
        private OrderSettlement $orders,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function handle(HandlePaymentWebhookCommand $command): WebhookResultEnum
    {
        $event = $this->gateways->create($command->provider)->parseSignedWebhook($command->payload, $command->headers);

        return $this->transactions->run(
            fn (): WebhookResultEnum => $this->apply($command, $event),
            IsolationLevelEnum::READ_COMMITTED,
        );
    }

    private function apply(HandlePaymentWebhookCommand $command, PaymentWebhookEvent $event): WebhookResultEnum
    {
        if (!$this->inbox->recordIfNew($command->provider, $event->eventId)) {
            return WebhookResultEnum::DUPLICATE;
        }

        $payment = $this->payments->lockByProviderPaymentId($command->provider, $event->providerPaymentId)
            ?? throw new PaymentNotFoundException();

        if ($payment->status->isSettled()) {
            return WebhookResultEnum::DUPLICATE;
        }

        if (!$event->amount->equals($payment->amount)) {
            throw new PaymentAmountMismatchException();
        }

        $this->payments->updateStatus($payment->id, match ($event->outcome) {
            PaymentOutcomeEnum::FAILED => PaymentStatusEnum::FAILED,
            PaymentOutcomeEnum::SUCCEEDED => $this->orders->markPaid($payment->orderId)
                ? PaymentStatusEnum::SUCCEEDED
                : PaymentStatusEnum::REFUND_REQUIRED,
        });

        return WebhookResultEnum::PROCESSED;
    }
}
