<?php

declare(strict_types=1);

namespace App\Cart\Exception;

use DomainException;

final class CartNotOpenException extends DomainException
{
    private const string MESSAGE = 'The cart has already been checked out and can no longer change.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
