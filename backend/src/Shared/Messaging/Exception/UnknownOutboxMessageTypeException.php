<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Exception;

use LogicException;

final class UnknownOutboxMessageTypeException extends LogicException
{
    private const string MESSAGE_FORMAT = 'No outbox message class is registered for type "%s".';

    public function __construct(string $type)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $type));
    }
}
