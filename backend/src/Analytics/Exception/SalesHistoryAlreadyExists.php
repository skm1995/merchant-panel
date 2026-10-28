<?php

declare(strict_types=1);

namespace App\Analytics\Exception;

use RuntimeException;

final class SalesHistoryAlreadyExists extends RuntimeException
{
    private const string MESSAGE = 'The sales history already exists; remove it first with bin/remove-sales-history.php.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
