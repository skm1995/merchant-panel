<?php

declare(strict_types=1);

namespace App\Order\Exception;

use DomainException;

final class OrderVersionMismatchException extends DomainException
{
    private const string MESSAGE = 'The order has changed since it was read; fetch it again and retry with its current ETag.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
