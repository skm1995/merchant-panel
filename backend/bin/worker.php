<?php

declare(strict_types=1);

use App\Shared\Config\Environment;
use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\MessagingDiscovery;
use App\Shared\Messaging\MessengerRuntime;
use App\Simulation\Command\RunSimulationCommand;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;

require dirname(__DIR__) . '/vendor/autoload.php';

const TIME_LIMIT_VARIABLE = 'WORKER_TIME_LIMIT_SECONDS';

$container = ContainerFactory::create();
$handlers = $container->get(MessagingDiscovery::class)->handlers($container);

$runtime = $container->get(MessengerRuntime::class);
// The Black Friday simulation runs in its own process, bin/simulator.php.
// The process keeps its database connection, and MySQL closes one left idle for hours, which
// would fail every later message. It exits in time, and Docker starts a fresh one.
$runtime->worker(
    $handlers,
    array_values(array_diff($runtime->transportNames(), [RunSimulationCommand::TRANSPORT])),
    [new StopWorkerOnTimeLimitListener(Environment::positiveInteger(TIME_LIMIT_VARIABLE))],
)->run();
