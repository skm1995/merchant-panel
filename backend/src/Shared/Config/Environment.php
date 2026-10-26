<?php

declare(strict_types=1);

namespace App\Shared\Config;

use App\Shared\Config\Exception\InvalidEnvironmentVariableException;
use App\Shared\Config\Exception\MissingEnvironmentVariableException;

final class Environment
{
    private const string POSITIVE_INTEGER_EXPECTATION = 'a positive integer';
    private const int MINIMUM_POSITIVE_INTEGER = 1;

    public static function find(string $key): ?string
    {
        $value = getenv($key);

        return is_string($value) && strlen($value) > 0 ? $value : null;
    }

    public static function get(string $key): string
    {
        return self::find($key) ?? throw new MissingEnvironmentVariableException($key);
    }

    public static function positiveInteger(string $key): int
    {
        $value = filter_var(self::get($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MINIMUM_POSITIVE_INTEGER],
        ]);

        return $value !== false
            ? $value
            : throw new InvalidEnvironmentVariableException($key, self::POSITIVE_INTEGER_EXPECTATION);
    }
}
