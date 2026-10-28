<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Shared\Messaging\Bus\CommandInterface;

/** @implements CommandInterface<string> the id of the started simulation */
final readonly class StartSimulationCommand implements CommandInterface
{
}
