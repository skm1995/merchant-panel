<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use DomainException;
use Throwable;

final class DuplicateEmailException extends DomainException
{
    private const string MESSAGE = 'An account with this email already exists.';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}
