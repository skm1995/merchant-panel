<?php

declare(strict_types=1);

namespace App\Payment\Command;

use App\Order\Entity\OrderId;
use App\Payment\Enum\PaymentProviderEnum;

final readonly class StartPaymentCommand
{
    public function __construct(
        public OrderId $orderId,
        public PaymentProviderEnum $provider,
        public string $idempotencyKey,
    ) {
    }
}
