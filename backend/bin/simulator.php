<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\MessagingDiscovery;
use App\Shared\Messaging\MessengerRuntime;
use App\Simulation\Command\RunSimulationCommand;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;

require dirname(__DIR__) . '/vendor/autoload.php';

// Runs Black Friday simulations: consumes only the simulation transport, so the load a run
// generates never delays the worker's emails, expiries, or summaries.
// One run per process: the process keeps the database connection its first run opened, and
// MySQL closes a connection left idle for hours, so the next run's check would fail. Docker
// starts a fresh process for the next run, which opens a fresh connection.
$container = ContainerFactory::create();
$handlers = $container->get(MessagingDiscovery::class)->handlers($container);

$container->get(MessengerRuntime::class)
    ->worker($handlers, [RunSimulationCommand::TRANSPORT], [new StopWorkerOnMessageLimitListener(1)])
    ->run();
