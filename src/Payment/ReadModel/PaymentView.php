<?php

declare(strict_types=1);

namespace App\Payment\ReadModel;

use App\Order\Entity\OrderId;
use App\Payment\Entity\PaymentId;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use Money\Money;

final readonly class PaymentView
{
    public function __construct(
        public PaymentId $id,
        public OrderId $orderId,
        public PaymentProviderEnum $provider,
        public PaymentStatusEnum $status,
        public Money $amount,
        public string $checkoutUrl,
    ) {
    }
}
