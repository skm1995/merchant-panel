<?php

declare(strict_types=1);

namespace App\Cart\Exception;

use DomainException;

final class InvalidCartQuantityException extends DomainException
{
    private const string MESSAGE_FORMAT = 'Quantity must be between 0 and %d; 0 removes the item.';

    public function __construct(int $maximumQuantity)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $maximumQuantity));
    }
}
