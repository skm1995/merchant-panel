<?php

declare(strict_types=1);

namespace App\Shared\Config\Exception;

use RuntimeException;

final class InvalidEnvironmentVariableException extends RuntimeException
{
    private const string MESSAGE_FORMAT = 'Environment variable "%s" must be %s.';

    public function __construct(string $key, string $expectation)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $key, $expectation));
    }
}
