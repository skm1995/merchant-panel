<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Order\Entity\OrderId;

/**
 * Which order an idempotency key already started a payment for.
 */
final readonly class PaymentStart
{
    public function __construct(public PaymentId $paymentId, public OrderId $orderId)
    {
    }
}
