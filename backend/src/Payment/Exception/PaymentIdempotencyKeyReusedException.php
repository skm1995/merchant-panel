<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class PaymentIdempotencyKeyReusedException extends DomainException
{
    private const string MESSAGE = 'This Idempotency-Key was already used for a different order.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
