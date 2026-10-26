<?php

declare(strict_types=1);

namespace App\Catalog\Exception;

use DomainException;

final class StockBelowReservationsException extends DomainException
{
    private const string MESSAGE_FORMAT = 'Stock cannot be lower than the %d units reserved by open orders.';

    public function __construct(int $reserved)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $reserved));
    }
}
