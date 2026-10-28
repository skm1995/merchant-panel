<?php

declare(strict_types=1);

use Tests\Functional\Support\ApiCallLog;
use Tests\Support\OpenApiOperations;

require __DIR__ . '/../vendor/autoload.php';

// Fails bin/ci when an operation of the API contract was never called by a functional test,
// so every endpoint keeps a test that goes through the real web server.
const LINE_SEPARATOR = "\n";
const FIELD_SEPARATOR = ' ';
const SUMMARY_FORMAT = "Functional tests called %d of %d operations of the API contract.\n";
const UNTESTED_FORMAT = "Not called by any functional test: %s\n";

$calls = array_filter(explode(LINE_SEPARATOR, (string) @file_get_contents(ApiCallLog::FILE)));
$operations = OpenApiOperations::all();
$untested = array_values(array_filter($operations, static function (string $operation) use ($calls): bool {
    foreach ($calls as $call) {
        [$method, $url] = explode(FIELD_SEPARATOR, $call, 2);

        if (OpenApiOperations::matches($operation, $method, $url)) {
            return false;
        }
    }

    return true;
}));

fwrite(STDOUT, sprintf(SUMMARY_FORMAT, count($operations) - count($untested), count($operations)));

foreach ($untested as $operation) {
    fwrite(STDERR, sprintf(UNTESTED_FORMAT, $operation));
}

exit($untested === [] ? 0 : 1);
