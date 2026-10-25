<?php

declare(strict_types=1);

use App\Auth\Message\ConfirmationEmailMessage;
use App\Shared\Messaging\MessengerRuntime;

/*
 * Messenger transports and routing. Transport DSNs come from the environment; each
 * message class is sent to one transport and consumed by bin/worker.php.
 */
return [
    'transports' => [
        'emails' => 'MESSENGER_EMAILS_DSN',
        MessengerRuntime::FAILED_TRANSPORT => 'MESSENGER_FAILED_DSN',
    ],
    'routes' => [
        ConfirmationEmailMessage::class => 'emails',
    ],
];
