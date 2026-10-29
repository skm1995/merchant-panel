<?php

declare(strict_types=1);

namespace App\Payment\Query;

use App\Payment\Entity\PaymentId;
use App\Payment\ReadModel\PaymentView;
use App\Shared\Messaging\Bus\QueryInterface;

/** @implements QueryInterface<PaymentView> */
final readonly class GetPaymentQuery implements QueryInterface
{
    public function __construct(public PaymentId $paymentId, public string $orderAccessToken)
    {
    }
}
