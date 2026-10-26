<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class PaymentNotFoundException extends DomainException
{
    private const string MESSAGE = 'The payment does not exist.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
