<?php

declare(strict_types=1);

namespace App\Simulation\Query;

use App\Shared\Messaging\Bus\QueryInterface;
use App\Simulation\ReadModel\SimulationView;

/** @implements QueryInterface<SimulationView> */
final readonly class GetCurrentSimulationQuery implements QueryInterface
{
}
