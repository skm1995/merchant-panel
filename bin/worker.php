<?php

declare(strict_types=1);

use App\Service\Auth\AuthMessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

AuthMessengerRuntime::createFromEnvironment()->createWorker()->run();
