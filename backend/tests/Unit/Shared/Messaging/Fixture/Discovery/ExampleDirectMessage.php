<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Discovery;

use App\Shared\Messaging\Message;

#[Message(transport: 'direct')]
final readonly class ExampleDirectMessage
{
}
