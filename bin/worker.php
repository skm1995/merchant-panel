<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Messenger\AuthMessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

AuthMessengerRuntime::createWorkerFromEnvironment()->run();
