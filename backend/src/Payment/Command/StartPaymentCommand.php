<?php

declare(strict_types=1);

namespace App\Payment\Command;

use App\Payment\Entity\PaymentId;
use App\Shared\Messaging\Bus\CommandInterface;
use App\Order\Entity\OrderId;
use App\Payment\Enum\PaymentProviderEnum;

/** @implements CommandInterface<PaymentId> */
final readonly class StartPaymentCommand implements CommandInterface
{
    public function __construct(
        public OrderId $orderId,
        public string $orderAccessToken,
        public PaymentProviderEnum $provider,
        public string $idempotencyKey,
    ) {
    }
}
