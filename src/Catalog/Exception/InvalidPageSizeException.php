<?php

declare(strict_types=1);

namespace App\Catalog\Exception;

use DomainException;

final class InvalidPageSizeException extends DomainException
{
    private const string MESSAGE_FORMAT = 'Page size must be between 1 and %d.';

    public function __construct(int $maximumPageSize)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $maximumPageSize));
    }
}
