<?php

declare(strict_types=1);

namespace App\Catalog\Exception;

use DomainException;

final class ProductVersionMismatchException extends DomainException
{
    private const string MESSAGE = 'The product changed since you loaded it; reload it and try again.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
