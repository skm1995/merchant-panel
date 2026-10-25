<?php

declare(strict_types=1);

namespace App\Shared\Config;

use RuntimeException;

final class MissingEnvironmentVariableException extends RuntimeException
{
    private const string MESSAGE_FORMAT = 'Required environment variable "%s" is not set.';

    public function __construct(string $key)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $key));
    }
}
