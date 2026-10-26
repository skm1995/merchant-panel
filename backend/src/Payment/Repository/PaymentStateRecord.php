<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Order\Entity\OrderId;
use App\Payment\Entity\PaymentId;
use App\Payment\Entity\PaymentState;
use App\Payment\Enum\PaymentStatusEnum;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<PaymentState> */
#[HydratedRecord]
final readonly class PaymentStateRecord implements EntityRecord
{
    public string $paymentId;
    public string $orderId;
    public string $status;
    public int $amount;
    /** @var non-empty-string */
    public string $currency;

    public function toEntity(): PaymentState
    {
        return new PaymentState(
            PaymentId::fromBinary($this->paymentId),
            OrderId::fromBinary($this->orderId),
            PaymentStatusEnum::from($this->status),
            new Money($this->amount, new Currency($this->currency)),
        );
    }
}
