<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\MessagingDiscovery;
use App\Shared\Messaging\MessengerRuntime;
use App\Simulation\Command\RunSimulationCommand;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = ContainerFactory::create();
$handlers = $container->get(MessagingDiscovery::class)->handlers($container);

$runtime = $container->get(MessengerRuntime::class);
// The Black Friday simulation runs in its own process, bin/simulator.php.
$runtime->worker($handlers, array_values(array_diff($runtime->transportNames(), [RunSimulationCommand::TRANSPORT])))->run();
