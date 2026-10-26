<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class PaymentAmountMismatchException extends DomainException
{
    private const string MESSAGE = 'The reported amount differs from the payment amount.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
