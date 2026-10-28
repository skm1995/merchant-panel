<?php

declare(strict_types=1);

namespace App\Simulation\Controller;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\RequireMerchant;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;
use App\Shared\Messaging\Bus\CommandBusInterface;
use App\Shared\Messaging\Bus\QueryBusInterface;
use App\Simulation\Command\StartSimulationCommand;
use App\Simulation\Exception\SimulationNotFoundException;
use App\Simulation\Exception\SimulationUnavailableException;
use App\Simulation\Query\GetCurrentSimulationQuery;
use App\Simulation\Response\SimulationResponse;

final readonly class SimulationApiController
{
    private const string SIMULATIONS_ROUTE_PATH = '/api/v1/panel/simulations';
    private const string CURRENT_ROUTE_PATH = '/api/v1/panel/simulations/current';

    public function __construct(
        private CommandBusInterface $commands,
        private QueryBusInterface $queries,
        private Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::POST, self::SIMULATIONS_ROUTE_PATH)]
    #[RequireMerchant]
    #[MapException(SimulationUnavailableException::class, ResponseStatusEnum::CONFLICT)]
    public function start(): void
    {
        $this->commands->dispatch(new StartSimulationCommand());

        $this->responder->json(new SimulationResponse($this->queries->ask(new GetCurrentSimulationQuery())), ResponseStatusEnum::ACCEPTED);
    }

    #[Route(HttpMethodEnum::GET, self::CURRENT_ROUTE_PATH)]
    #[RequireMerchant]
    #[MapException(SimulationNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function current(): void
    {
        $this->responder->json(new SimulationResponse($this->queries->ask(new GetCurrentSimulationQuery())));
    }
}
