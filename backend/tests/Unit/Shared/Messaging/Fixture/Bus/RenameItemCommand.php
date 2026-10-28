<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Bus;

use App\Shared\Messaging\Bus\CommandInterface;

/** @implements CommandInterface<string> */
final readonly class RenameItemCommand implements CommandInterface
{
    public function __construct(public string $name)
    {
    }
}
