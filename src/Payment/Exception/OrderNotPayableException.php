<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class OrderNotPayableException extends DomainException
{
    private const string MESSAGE = 'Only orders awaiting payment can be paid.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
