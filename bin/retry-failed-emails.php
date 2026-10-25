<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Messenger\AuthMessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

$retriedCount = AuthMessengerRuntime::retryFailedMessagesFromEnvironment();
fwrite(STDOUT, sprintf("Requeued %d failed email message(s).%s", $retriedCount, PHP_EOL));
