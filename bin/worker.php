<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\MessagingDiscovery;
use App\Shared\Messaging\MessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = ContainerFactory::create();
$handlers = $container->get(MessagingDiscovery::class)->handlers($container);

$container->get(MessengerRuntime::class)->worker($handlers)->run();
