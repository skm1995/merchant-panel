<?php

declare(strict_types=1);

namespace App\Payment\Query;

use App\Order\Exception\OrderNotFoundException;
use App\Order\Service\OrderAccess;
use App\Payment\Exception\PaymentNotFoundException;
use App\Payment\ReadModel\PaymentView;
use App\Payment\Repository\PaymentReadRepositoryInterface;

final class GetPaymentHandler
{
    public function __construct(
        private readonly PaymentReadRepositoryInterface $payments,
        private readonly OrderAccess $access,
    ) {
    }

    public function handle(GetPaymentQuery $query): PaymentView
    {
        $payment = $this->payments->find($query->paymentId) ?? throw new PaymentNotFoundException();

        try {
            $this->access->verify($payment->orderId, $query->orderAccessToken);
        } catch (OrderNotFoundException) {
            throw new PaymentNotFoundException();
        }

        return $payment;
    }
}
