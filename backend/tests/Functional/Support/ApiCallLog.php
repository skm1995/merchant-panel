<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

/**
 * Notes every request the functional tests send, one "METHOD /path" per line, so
 * tools/check-api-calls.php can tell bin/ci whether every operation of the contract was tested.
 */
final class ApiCallLog
{
    public const string FILE = __DIR__ . '/../../../.phpunit.cache/api-calls.log';
    private const string LINE_FORMAT = "%s %s\n";

    public static function record(string $method, string $path): void
    {
        file_put_contents(self::FILE, sprintf(self::LINE_FORMAT, $method, $path), FILE_APPEND | LOCK_EX);
    }
}
