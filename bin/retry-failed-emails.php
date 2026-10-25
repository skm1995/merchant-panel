<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Messenger\AuthMessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

const SUMMARY_FORMAT = 'Requeued %d failed email message(s); %d undecodable message(s) left in the failure queue.%s';

$result = AuthMessengerRuntime::retryFailedMessagesFromEnvironment();
fwrite(STDOUT, sprintf(SUMMARY_FORMAT, $result->retried, $result->skipped, PHP_EOL));
