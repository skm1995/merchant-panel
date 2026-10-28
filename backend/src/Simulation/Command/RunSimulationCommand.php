<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

/**
 * Recorded in the outbox when a simulation starts and delivered to the simulator, the only
 * process that consumes the simulation transport, so the load never runs in the web server
 * or the worker.
 */
#[Message(transport: self::TRANSPORT)]
final readonly class RunSimulationCommand implements OutboxMessageInterface
{
    public const string TRANSPORT = 'simulation';
    private const string TYPE = 'simulation.run';
    private const string SIMULATION_ID_KEY = 'simulation_id';

    public function __construct(public string $simulationId)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data[self::SIMULATION_ID_KEY]);
    }

    public function toArray(): array
    {
        return [self::SIMULATION_ID_KEY => $this->simulationId];
    }
}
