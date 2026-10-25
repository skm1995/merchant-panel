<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Order\Entity\OrderId;
use App\Payment\Entity\PaymentId;
use App\Payment\Entity\PaymentStart;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;

/** @implements EntityRecord<PaymentStart> */
#[HydratedRecord]
final readonly class PaymentStartRecord implements EntityRecord
{
    public string $paymentId;
    public string $orderId;

    public function toEntity(): PaymentStart
    {
        return new PaymentStart(PaymentId::fromBinary($this->paymentId), OrderId::fromBinary($this->orderId));
    }
}
