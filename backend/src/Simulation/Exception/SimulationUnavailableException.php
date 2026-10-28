<?php

declare(strict_types=1);

namespace App\Simulation\Exception;

use RuntimeException;

final class SimulationUnavailableException extends RuntimeException
{
    private const string MESSAGE_FORMAT = 'A simulation is running or has just finished; the next one can start in %d seconds.';

    public function __construct(int $secondsUntilFree)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $secondsUntilFree));
    }
}
