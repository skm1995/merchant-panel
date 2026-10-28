<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\MessagingDiscovery;
use App\Shared\Messaging\MessengerRuntime;
use App\Simulation\Command\RunSimulationCommand;

require dirname(__DIR__) . '/vendor/autoload.php';

// Runs Black Friday simulations: consumes only the simulation transport, so the load a run
// generates never delays the worker's emails, expiries, or summaries.
$container = ContainerFactory::create();
$handlers = $container->get(MessagingDiscovery::class)->handlers($container);

$container->get(MessengerRuntime::class)->worker($handlers, [RunSimulationCommand::TRANSPORT])->run();
