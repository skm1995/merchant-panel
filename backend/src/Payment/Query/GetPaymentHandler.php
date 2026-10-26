<?php

declare(strict_types=1);

namespace App\Payment\Query;

use App\Payment\Exception\PaymentNotFoundException;
use App\Payment\ReadModel\PaymentView;
use App\Payment\Repository\PaymentReadRepositoryInterface;

final class GetPaymentHandler
{
    public function __construct(private readonly PaymentReadRepositoryInterface $payments)
    {
    }

    public function handle(GetPaymentQuery $query): PaymentView
    {
        return $this->payments->find($query->paymentId) ?? throw new PaymentNotFoundException();
    }
}
