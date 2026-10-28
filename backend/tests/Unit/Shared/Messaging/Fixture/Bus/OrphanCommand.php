<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Bus;

use App\Shared\Messaging\Bus\CommandInterface;

/**
 * A command without a handler class next to it.
 *
 * @implements CommandInterface<null>
 */
final readonly class OrphanCommand implements CommandInterface
{
}
