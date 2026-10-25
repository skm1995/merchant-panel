<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class UnknownPaymentProviderException extends DomainException
{
    private const string MESSAGE = 'The payment provider is not supported.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
