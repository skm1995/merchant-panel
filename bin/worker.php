<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Messenger\SendConfirmationEmailHandler;
use App\Auth\Message\ConfirmationEmailMessage;
use App\Catalog\Infrastructure\Cache\ProductCache;
use App\Catalog\Message\ProductStockChangedMessage;
use App\Order\Command\ExpireOrderReservationCommand;
use App\Order\Command\ExpireOrderReservationHandler;
use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\MessengerRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = ContainerFactory::create();

$container->get(MessengerRuntime::class)->worker([
    ConfirmationEmailMessage::class => [$container->get(SendConfirmationEmailHandler::class)],
    ExpireOrderReservationCommand::class => [$container->get(ExpireOrderReservationHandler::class)->handle(...)],
    ProductStockChangedMessage::class => [$container->get(ProductCache::class)->handle(...)],
])->run();
