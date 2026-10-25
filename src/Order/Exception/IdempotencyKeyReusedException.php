<?php

declare(strict_types=1);

namespace App\Order\Exception;

use DomainException;
use Throwable;

final class IdempotencyKeyReusedException extends DomainException
{
    private const string MESSAGE = 'This Idempotency-Key was already used for a different cart.';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}
