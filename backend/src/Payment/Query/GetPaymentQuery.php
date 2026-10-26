<?php

declare(strict_types=1);

namespace App\Payment\Query;

use App\Payment\Entity\PaymentId;

final readonly class GetPaymentQuery
{
    public function __construct(public PaymentId $paymentId)
    {
    }
}
