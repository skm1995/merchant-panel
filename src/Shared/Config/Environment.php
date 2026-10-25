<?php

declare(strict_types=1);

namespace App\Shared\Config;

use App\Shared\Config\Exception\MissingEnvironmentVariableException;

final class Environment
{
    public static function find(string $key): ?string
    {
        $value = getenv($key);

        return is_string($value) && strlen($value) > 0 ? $value : null;
    }

    public static function get(string $key): string
    {
        return self::find($key) ?? throw new MissingEnvironmentVariableException($key);
    }
}
