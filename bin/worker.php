<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Messenger\SendConfirmationEmailHandler;
use App\Auth\Message\ConfirmationEmailMessage;
use App\Catalog\Repository\PdoProductRepository;
use App\Catalog\Service\StockReservation;
use App\Order\Command\ExpireOrderReservationCommand;
use App\Order\Command\ExpireOrderReservationHandler;
use App\Order\Repository\PdoOrderRepository;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Messaging\MessengerRuntime;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

require dirname(__DIR__) . '/vendor/autoload.php';

const MAILER_DSN = 'MAILER_DSN';
const APP_BASE_URL = 'APP_BASE_URL';
const MAILER_FROM = 'MAILER_FROM';

$mailer = new Mailer(Transport::fromDsn(Environment::get(MAILER_DSN)));
$pdo = DatabaseConnection::createFromEnvironment();
$expireReservation = new ExpireOrderReservationHandler(
    new PdoOrderRepository($pdo),
    new StockReservation(new PdoProductRepository($pdo)),
    new PdoTransactionRunner($pdo),
);

MessengerRuntime::fromConfiguration()->worker([
    ConfirmationEmailMessage::class => [
        new SendConfirmationEmailHandler($mailer, Environment::get(APP_BASE_URL), Environment::get(MAILER_FROM)),
    ],
    ExpireOrderReservationCommand::class => [$expireReservation->handle(...)],
])->run();
