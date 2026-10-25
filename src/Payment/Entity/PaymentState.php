<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Order\Entity\OrderId;
use App\Payment\Enum\PaymentStatusEnum;
use Money\Money;

/**
 * Write-side snapshot of a payment used to settle it from a provider webhook.
 */
final readonly class PaymentState
{
    public function __construct(
        public PaymentId $id,
        public OrderId $orderId,
        public PaymentStatusEnum $status,
        public Money $amount,
    ) {
    }
}
