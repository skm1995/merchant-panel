<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Order\Entity\OrderId;
use App\Payment\Entity\PaymentId;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\ReadModel\PaymentView;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<PaymentView> */
#[HydratedRecord]
final readonly class PaymentViewRecord implements EntityRecord
{
    public string $paymentId;
    public string $orderId;
    public string $provider;
    public string $status;
    public int $amount;
    /** @var non-empty-string */
    public string $currency;
    public string $checkoutUrl;

    public function toEntity(): PaymentView
    {
        return new PaymentView(
            PaymentId::fromBinary($this->paymentId),
            OrderId::fromBinary($this->orderId),
            PaymentProviderEnum::from($this->provider),
            PaymentStatusEnum::from($this->status),
            new Money($this->amount, new Currency($this->currency)),
            $this->checkoutUrl,
        );
    }
}
