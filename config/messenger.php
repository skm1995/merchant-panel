<?php

declare(strict_types=1);

use App\Auth\Message\ConfirmationEmailMessage;
use App\Order\Command\ExpireOrderReservationCommand;
use App\Shared\Messaging\MessengerRuntime;

/*
 * Messenger transports and routing. Transport DSNs come from the environment; each
 * message class is sent to one transport and consumed by bin/worker.php. Outbox message
 * classes are the ones bin/outbox-relay.php may publish from the outbox table.
 */
return [
    'transports' => [
        'emails' => 'MESSENGER_EMAILS_DSN',
        'orders' => 'MESSENGER_ORDERS_DSN',
        MessengerRuntime::FAILED_TRANSPORT => 'MESSENGER_FAILED_DSN',
    ],
    'routes' => [
        ConfirmationEmailMessage::class => 'emails',
        ExpireOrderReservationCommand::class => 'orders',
    ],
    'outbox_messages' => [
        ExpireOrderReservationCommand::class,
    ],
];
