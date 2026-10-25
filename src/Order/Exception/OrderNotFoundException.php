<?php

declare(strict_types=1);

namespace App\Order\Exception;

use DomainException;

final class OrderNotFoundException extends DomainException
{
    private const string MESSAGE = 'The order does not exist.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
