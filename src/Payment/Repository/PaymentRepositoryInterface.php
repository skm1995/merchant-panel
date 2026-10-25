<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Entity\Payment;
use App\Payment\Entity\PaymentId;
use App\Payment\Entity\PaymentStart;
use App\Payment\Entity\PaymentState;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;

interface PaymentRepositoryInterface
{
    /**
     * @throws \App\Payment\Exception\PaymentIdempotencyKeyReusedException when another payment holds the key
     */
    public function add(Payment $payment): void;

    public function findStart(string $idempotencyKey): ?PaymentStart;

    /**
     * Locks the payment row until the surrounding transaction ends.
     */
    public function lockByProviderPaymentId(PaymentProviderEnum $provider, string $providerPaymentId): ?PaymentState;

    public function updateStatus(PaymentId $id, PaymentStatusEnum $status): void;
}
