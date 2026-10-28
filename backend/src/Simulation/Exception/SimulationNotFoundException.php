<?php

declare(strict_types=1);

namespace App\Simulation\Exception;

use RuntimeException;

final class SimulationNotFoundException extends RuntimeException
{
    private const string MESSAGE = 'No simulation has run yet.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
