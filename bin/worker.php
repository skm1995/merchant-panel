<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

App\Service\Auth\AuthMessengerRuntime::createFromEnvironment()->createWorker()->run();
