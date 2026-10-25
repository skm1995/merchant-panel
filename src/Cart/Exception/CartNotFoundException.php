<?php

declare(strict_types=1);

namespace App\Cart\Exception;

use DomainException;

final class CartNotFoundException extends DomainException
{
    private const string MESSAGE = 'The cart does not exist.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
