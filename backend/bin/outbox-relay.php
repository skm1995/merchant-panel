<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Messaging\Outbox\OutboxRelay;

require dirname(__DIR__) . '/vendor/autoload.php';

const IDLE_POLL_MICROSECONDS = 200000;
// The process exits after this many polls and is restarted by its supervisor, which keeps
// long-running PHP memory in check, like messenger:consume --limit.
const MAXIMUM_POLLS = 3000;

$relay = ContainerFactory::create()->get(OutboxRelay::class);

for ($poll = 0; $poll < MAXIMUM_POLLS; $poll++) {
    if ($relay->relayBatch() === 0) {
        usleep(IDLE_POLL_MICROSECONDS);
    }
}
