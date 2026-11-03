<?php

declare(strict_types=1);

namespace App\Simulation\Enum;

enum SimulationStateEnum: string
{
    case RUNNING = 'running';
    case FINISHED = 'finished';
    case INTERRUPTED = 'interrupted';
}
