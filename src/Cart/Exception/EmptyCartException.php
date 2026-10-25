<?php

declare(strict_types=1);

namespace App\Cart\Exception;

use DomainException;

final class EmptyCartException extends DomainException
{
    private const string MESSAGE = 'An empty cart cannot be checked out.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
