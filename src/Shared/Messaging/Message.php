<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Attribute;

/**
 * Routes a message class to a Messenger transport. The transport DSN is read from the
 * MESSENGER_<TRANSPORT>_DSN environment variable.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Message
{
    public function __construct(public string $transport)
    {
    }
}
