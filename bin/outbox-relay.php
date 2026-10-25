<?php

declare(strict_types=1);

use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Messaging\MessengerRuntime;
use App\Shared\Messaging\Outbox\OutboxRelay;
use App\Shared\Messaging\Outbox\PdoOutbox;
use Symfony\Component\Clock\NativeClock;

require dirname(__DIR__) . '/vendor/autoload.php';

const IDLE_POLL_MICROSECONDS = 200000;
// The process exits after this many polls and is restarted by its supervisor, which keeps
// long-running PHP memory in check, like messenger:consume --limit.
const MAXIMUM_POLLS = 3000;

$pdo = DatabaseConnection::createFromEnvironment();
$messenger = MessengerRuntime::fromConfiguration();
$relay = new OutboxRelay(
    new PdoOutbox($pdo),
    new PdoTransactionRunner($pdo),
    $messenger->bus(),
    $messenger->outboxMessageTypes(),
    new NativeClock(),
);

for ($poll = 0; $poll < MAXIMUM_POLLS; $poll++) {
    if ($relay->relayBatch() === 0) {
        usleep(IDLE_POLL_MICROSECONDS);
    }
}
