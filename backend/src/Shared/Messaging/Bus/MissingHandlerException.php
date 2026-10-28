<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

use LogicException;

final class MissingHandlerException extends LogicException
{
    private const string MESSAGE_FORMAT = '%s needs a handler class %s with a handle() method.';

    public function __construct(string $messageClass, string $handlerClass)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $messageClass, $handlerClass));
    }
}
