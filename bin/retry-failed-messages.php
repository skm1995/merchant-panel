<?php

declare(strict_types=1);

use App\Shared\Messaging\MessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

const SUMMARY_FORMAT = 'Requeued %d failed message(s); %d undecodable message(s) left in the failure queue.%s';

$result = MessengerRuntime::fromConfiguration()->failedMessageRetrier()->retryAvailable();
fwrite(STDOUT, sprintf(SUMMARY_FORMAT, $result->retried, $result->skipped, PHP_EOL));
