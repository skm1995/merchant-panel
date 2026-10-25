<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Exception;

use LogicException;

final class UnknownTransportException extends LogicException
{
    private const string MESSAGE_FORMAT = 'No messenger transport named "%s" is configured.';

    public function __construct(string $name)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $name));
    }
}
